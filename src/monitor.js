const https = require('https');
const http = require('http');
const net = require('net');
const mysql = require('mysql2/promise');
const axios = require('axios');
const tls = require('tls');
const config = require('./utils/config');
const { logger, monitorCheck, monitorError } = require('./utils/logger');

class UptimeMonitor {
    constructor() {
        this.monitors = new Map();
        this.db = null;
    }

    async initialize() {
        // Initialize database connection
        this.db = await mysql.createConnection({
            host: config.db.host,
            user: config.db.user,
            password: config.db.password,
            database: config.db.name
        });

        // Create tables if they don't exist
        await this.initializeTables();
        
        logger.info('UptimeMonitor initialized successfully');
    }

    async initializeTables() {
        const queries = [
            `CREATE TABLE IF NOT EXISTS monitors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                url VARCHAR(255) NOT NULL,
                type ENUM('http', 'tcp', 'ssl') NOT NULL,
                interval_seconds INT NOT NULL,
                port INT,
                webhook_url VARCHAR(255),
                active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )`,
            `CREATE TABLE IF NOT EXISTS monitor_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                monitor_id INT NOT NULL,
                status BOOLEAN NOT NULL,
                response_time INT,
                error_message TEXT,
                checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
            )`
        ];
    
        for (const query of queries) {
            await this.db.execute(query);
        }
    }

    async startMonitor(monitorId) {
        const [rows] = await this.db.execute(
            'SELECT * FROM monitors WHERE id = ?',
            [monitorId]
        );

        if (rows.length === 0) {
            throw new Error('Monitor not found');
        }

        const monitor = rows[0];
        
        // Stop existing monitor if running
        if (this.monitors.has(monitorId)) {
            this.stopMonitor(monitorId);
        }

        const checkFunction = async () => {
            try {
                let status = false;
                let responseTime = null;
                let errorMessage = null;

                const startTime = Date.now();

                switch (monitor.type) {
                    case 'http':
                        status = await this.checkHttp(monitor.url);
                        break;
                    case 'tcp':
                        status = await this.checkTcp(monitor.url, monitor.port);
                        break;
                    case 'ssl':
                        const sslStatus = await this.checkSsl(monitor.url);
                        status = sslStatus.valid;
                        errorMessage = sslStatus.daysRemaining ? 
                            `SSL certificate expires in ${sslStatus.daysRemaining} days` : 
                            sslStatus.error;
                        break;
                }

                responseTime = Date.now() - startTime;

                // Log result
                await this.logResult(monitor.id, status, responseTime, errorMessage);

                // Send webhook if status changed
                const previousStatus = await this.getPreviousStatus(monitor.id);
                if (previousStatus !== null && previousStatus !== status && monitor.webhook_url) {
                    await this.sendWebhook(monitor, status, responseTime, errorMessage);
                }

            } catch (error) {
                logger.error(`Error checking monitor ${monitor.id}:`, error);
                await this.logResult(monitor.id, false, null, error.message);
            }
        };

        // Start the interval
        const intervalId = setInterval(checkFunction, monitor.interval_seconds * 1000);
        this.monitors.set(monitorId, intervalId);

        // Run first check immediately
        await checkFunction();
    }

    stopMonitor(monitorId) {
        const intervalId = this.monitors.get(monitorId);
        if (intervalId) {
            clearInterval(intervalId);
            this.monitors.delete(monitorId);
        }
    }

    async checkHttp(url) {
        try {
            const response = await axios.get(url, {
                timeout: config.monitor.timeouts.http,
                validateStatus: null, // Don't throw on error status codes
                maxRedirects: 5
            });
            
            // Consider 200-399 as success, anything else (including 4xx and 5xx) as down
            const status = response.status >= 200 && response.status < 400;
            
            // Log detailed information for debugging
            logger.info(`HTTP check for ${url}: status code ${response.status} - ${status ? 'UP' : 'DOWN'}`);
            
            return status;
        } catch (error) {
            // Log detailed error information
            if (error.response) {
                // The request was made and the server responded with a status code
                // that falls out of the range of 2xx
                logger.error(`HTTP check failed for ${url}: Status ${error.response.status}`);
            } else if (error.request) {
                // The request was made but no response was received
                logger.error(`HTTP check failed for ${url}: No response received`);
            } else {
                // Something happened in setting up the request that triggered an Error
                logger.error(`HTTP check failed for ${url}: ${error.message}`);
            }
            
            return false;
        }
    }

    async checkTcp(host, port) {
        return new Promise((resolve) => {
            const socket = new net.Socket();
            socket.setTimeout(config.monitor.timeouts.tcp);
            socket.on('connect', () => {
                logger.info(`TCP check for ${host}:${port} succeeded`);
                socket.destroy();
                resolve(true);
            });
            socket.on('error', (err) => {
                logger.error(`TCP check for ${host}:${port} failed: ${err.message}`);
                socket.destroy();
                resolve(false);
            });
            socket.on('timeout', () => {
                logger.error(`TCP check for ${host}:${port} timed out`);
                socket.destroy();
                resolve(false);
            });
            socket.connect(port, host);
        });
    }

    async checkSsl(host) {
        return new Promise((resolve) => {
            const socket = tls.connect({
                host: host,
                port: 443,
                timeout: config.monitor.timeouts.ssl,
            }, () => {
                const cert = socket.getPeerCertificate();
                const valid = socket.authorized;
                
                if (valid && cert) {
                    const daysRemaining = Math.floor(
                        (new Date(cert.valid_to) - new Date()) / (1000 * 60 * 60 * 24)
                    );
                    socket.destroy();
                    resolve({ valid: true, daysRemaining });
                } else {
                    socket.destroy();
                    resolve({ valid: false, error: 'Invalid certificate' });
                }
            });

            socket.on('error', (error) => {
                socket.destroy();
                resolve({ valid: false, error: error.message });
            });

            socket.on('timeout', () => {
                socket.destroy();
                resolve({ valid: false, error: 'Connection timeout' });
            });
        });
    }

    async logResult(monitorId, status, responseTime, errorMessage) {
        try {
            // Get monitor info for more detailed logging
            const [monitorRows] = await this.db.execute(
                'SELECT name, url, type FROM monitors WHERE id = ?',
                [monitorId]
            );
            
            if (monitorRows.length === 0) {
                logger.error(`Failed to log result: Monitor with ID ${monitorId} not found`);
                return;
            }
            
            const monitor = monitorRows[0];
            
            // Get previous status for comparison
            const previousStatus = await this.getPreviousStatus(monitorId);
            
            // Insert the new log entry
            await this.db.execute(
                'INSERT INTO monitor_logs (monitor_id, status, response_time, error_message, checked_at) VALUES (?, ?, ?, ?, NOW())',
                [monitorId, status ? 1 : 0, responseTime, errorMessage]
            );
            
            // Log detailed information
            if (status) {
                logger.info(`Monitor ${monitorId} (${monitor.name}) is UP`, {
                    monitorId,
                    name: monitor.name,
                    url: monitor.url,
                    type: monitor.type,
                    status: 'UP',
                    responseTime,
                    statusChanged: previousStatus !== null && previousStatus !== status
                });
            } else {
                logger.error(`Monitor ${monitorId} (${monitor.name}) is DOWN`, {
                    monitorId,
                    name: monitor.name,
                    url: monitor.url,
                    type: monitor.type,
                    status: 'DOWN',
                    error: errorMessage,
                    statusChanged: previousStatus !== null && previousStatus !== status
                });
            }
            
            monitorCheck(monitorId, status, responseTime, errorMessage);
        } catch (error) {
            logger.error(`Error logging result for monitor ${monitorId}:`, error);
            monitorError(monitorId, error);
        }
    }

    async getPreviousStatus(monitorId) {
        const [rows] = await this.db.execute(
            'SELECT status FROM monitor_logs WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 1',
            [monitorId]
        );
        return rows.length > 0 ? rows[0].status : null;
    }

    async sendWebhook(monitor, status, responseTime, errorMessage) {
        try {
            await axios.post(monitor.webhook_url, {
                monitorId: monitor.id,
                name: monitor.name,
                url: monitor.url,
                status: status,
                responseTime: responseTime,
                errorMessage: errorMessage,
                timestamp: new Date().toISOString()
            });
        } catch (error) {
            logger.error(`Error sending webhook for monitor ${monitor.id}:`, error);
        }
    }
}

module.exports = UptimeMonitor;