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
            // Get monitor info
            const [monitorRows] = await this.db.execute(
                'SELECT name, url, type, webhook_url FROM monitors WHERE id = ?',
                [monitorId]
            );
            
            if (monitorRows.length === 0) {
                logger.error(`Failed to log result: Monitor with ID ${monitorId} not found`);
                return;
            }
            
            const monitor = monitorRows[0];
            
            // Get current status and any pending incident information
            const [statusRows] = await this.db.execute(
                'SELECT current_status, status_since, last_check_time FROM monitor_status WHERE monitor_id = ?',
                [monitorId]
            );
            
            const previousStatus = statusRows.length > 0 ? Boolean(statusRows[0].current_status) : null;
            const statusChanged = previousStatus !== null && previousStatus !== status;
            
            // Begin transaction
            await this.db.beginTransaction();
            
            try {
                // Check for existing open incidents to prevent duplicates
                const [existingIncidents] = await this.db.execute(
                    'SELECT id FROM monitor_incidents WHERE monitor_id = ? AND ended_at IS NULL',
                    [monitorId]
                );
                const hasOpenIncident = existingIncidents.length > 0;
                
                // Update monitor counters and last_check_time in all cases
                if (statusRows.length === 0) {
                    // First check for this monitor - insert with current status
                    await this.db.execute(
                        `INSERT INTO monitor_status (
                            monitor_id, current_status, status_since, last_check_time,
                            last_response_time, last_error_message, todays_checks,
                            todays_successful_checks, daily_uptime_percentage
                        ) VALUES (?, ?, NOW(), NOW(), ?, ?, 1, ?, 100)`,
                        [monitorId, status ? 1 : 0, responseTime, errorMessage, status ? 1 : 0]
                    );
                } else {
                    // For existing monitors, we need to handle status changes carefully
                    
                    // Case 1: No status change - just update check time and counters
                    if (!statusChanged) {
                        await this.db.execute(
                            `UPDATE monitor_status SET
                                last_check_time = NOW(),
                                last_response_time = ?,
                                last_error_message = ?,
                                todays_checks = todays_checks + 1,
                                todays_successful_checks = todays_successful_checks + ?,
                                daily_uptime_percentage = (todays_successful_checks * 100.0 / todays_checks)
                            WHERE monitor_id = ?`,
                            [responseTime, errorMessage, status ? 1 : 0, monitorId]
                        );
                    }
                    // Case 2: Status changing TO DOWN - track internally but don't update status_since yet
                    else if (status === false) {
                        // We'll update current_status but keep the same status_since for now
                        // This prevents brief outages from affecting "up since" time
                        await this.db.execute(
                            `UPDATE monitor_status SET
                                current_status = 0,
                                last_check_time = NOW(),
                                last_response_time = ?,
                                last_error_message = ?,
                                todays_checks = todays_checks + 1,
                                todays_successful_checks = todays_successful_checks + 0,
                                daily_uptime_percentage = (todays_successful_checks * 100.0 / todays_checks)
                            WHERE monitor_id = ?`,
                            [responseTime, errorMessage, monitorId]
                        );
                        
                        // Insert a temporary record to track the start of this potential outage
                        // We use a table variable or temporary table so it won't affect the status page
                        await this.db.execute(
                            `INSERT INTO monitor_potential_outages (monitor_id, started_at, error_message)
                             VALUES (?, NOW(), ?)
                             ON DUPLICATE KEY UPDATE started_at = NOW(), error_message = ?`,
                            [monitorId, errorMessage, errorMessage]
                        );
                        
                        logger.error(`Monitor ${monitorId} (${monitor.name}) changed status to DOWN - tracking potential outage`, {
                            monitorId,
                            name: monitor.name,
                            error: errorMessage
                        });
                    }
                    // Case 3: Status changing TO UP after being DOWN
                    else if (status === true) {
                        // Check if we had a potential outage and how long it lasted
                        const [potentialOutage] = await this.db.execute(
                            `SELECT started_at, TIMESTAMPDIFF(SECOND, started_at, NOW()) as duration_seconds
                             FROM monitor_potential_outages
                             WHERE monitor_id = ?`,
                            [monitorId]
                        );
                        
                        // If there was a potential outage that lasted 2+ minutes
                        if (potentialOutage.length > 0 && potentialOutage[0].duration_seconds >= 120) {
                            // 1. Update status_since to the outage start time (we're now officially considering this significant)
                            await this.db.execute(
                                `UPDATE monitor_status SET
                                    current_status = 1,
                                    status_since = NOW(), -- Reset status_since to now (recovery time)
                                    last_check_time = NOW(),
                                    last_response_time = ?,
                                    last_error_message = ?,
                                    todays_checks = todays_checks + 1,
                                    todays_successful_checks = todays_successful_checks + 1,
                                    daily_uptime_percentage = (todays_successful_checks * 100.0 / todays_checks)
                                WHERE monitor_id = ?`,
                                [responseTime, errorMessage, monitorId]
                            );
                            
                            // Close any existing open incidents to prevent duplicates
                            if (hasOpenIncident) {
                                await this.db.execute(
                                    `UPDATE monitor_incidents 
                                     SET ended_at = NOW(), 
                                         duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                                     WHERE monitor_id = ? AND ended_at IS NULL`,
                                    [monitorId]
                                );
                                logger.info(`Closed existing open incident for monitor ${monitorId} (${monitor.name})`);
                            } else {
                                // 2. Create a new closed incident record if none exists
                                await this.db.execute(
                                    `INSERT INTO monitor_incidents 
                                     (monitor_id, started_at, ended_at, error_message, duration_seconds)
                                     VALUES (?, ?, NOW(), ?, ?)`,
                                    [monitorId, potentialOutage[0].started_at, errorMessage, potentialOutage[0].duration_seconds]
                                );
                            }
                            
                            logger.info(`Monitor ${monitorId} (${monitor.name}) recovered after significant outage of ${potentialOutage[0].duration_seconds} seconds`, {
                                monitorId,
                                name: monitor.name
                            });
                            
                            // 3. Send recovery webhook
                            if (monitor.webhook_url) {
                                await this.sendWebhook(monitor, status, responseTime, null);
                            }
                        } else {
                            // This was a brief outage, just restore status without changing status_since
                            await this.db.execute(
                                `UPDATE monitor_status SET
                                    current_status = 1,
                                    last_check_time = NOW(),
                                    last_response_time = ?,
                                    last_error_message = ?,
                                    todays_checks = todays_checks + 1,
                                    todays_successful_checks = todays_successful_checks + 1,
                                    daily_uptime_percentage = (todays_successful_checks * 100.0 / todays_checks)
                                WHERE monitor_id = ?`,
                                [responseTime, errorMessage, monitorId]
                            );
                            
                            // Close any existing open incidents (shouldn't happen, but as a safeguard)
                            if (hasOpenIncident) {
                                await this.db.execute(
                                    `UPDATE monitor_incidents 
                                     SET ended_at = NOW(), 
                                         duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                                     WHERE monitor_id = ? AND ended_at IS NULL`,
                                    [monitorId]
                                );
                                logger.info(`Closed existing brief incident for monitor ${monitorId} (${monitor.name})`);
                            }
                            
                            logger.info(`Monitor ${monitorId} (${monitor.name}) recovered after brief outage - no incident recorded`, {
                                monitorId,
                                name: monitor.name
                            });
                        }
                        
                        // Clean up the potential outage entry
                        await this.db.execute(
                            `DELETE FROM monitor_potential_outages WHERE monitor_id = ?`,
                            [monitorId]
                        );
                    }
                }
                
                // Check if we need to create a significant outage incident while still DOWN
                if (status === false) {
                    // Check if we've been down long enough
                    const [potentialOutage] = await this.db.execute(
                        `SELECT started_at, TIMESTAMPDIFF(SECOND, started_at, NOW()) as duration_seconds
                         FROM monitor_potential_outages
                         WHERE monitor_id = ?`,
                        [monitorId]
                    );
                    
                    if (potentialOutage.length > 0) {
                        const duration = potentialOutage[0].duration_seconds;
                        
                        // If we just crossed the 2-minute threshold
                        if (duration >= 120 && duration < 120 + (monitor.interval_seconds || 60)) {
                            // It's been 2+ minutes, officially mark as a significant outage
                            
                            // 1. Update status_since to the outage start time
                            await this.db.execute(
                                `UPDATE monitor_status SET
                                    status_since = ?
                                WHERE monitor_id = ?`,
                                [potentialOutage[0].started_at, monitorId]
                            );
                            
                            // Only create an incident if there isn't already an open one
                            if (!hasOpenIncident) {
                                // 2. Create an open incident
                                await this.db.execute(
                                    `INSERT INTO monitor_incidents (monitor_id, started_at, error_message)
                                     VALUES (?, ?, ?)`,
                                    [monitorId, potentialOutage[0].started_at, errorMessage]
                                );
                                
                                logger.error(`Monitor ${monitorId} (${monitor.name}) has been DOWN for 2+ minutes - significant outage confirmed`, {
                                    monitorId,
                                    name: monitor.name,
                                    duration: duration
                                });
                                
                                // 3. Send outage webhook
                                if (monitor.webhook_url) {
                                    await this.sendWebhook(monitor, status, responseTime, errorMessage);
                                }
                            } else {
                                logger.info(`Monitor ${monitorId} (${monitor.name}) has been DOWN for 2+ minutes but incident already exists`, {
                                    monitorId,
                                    name: monitor.name,
                                    duration: duration
                                });
                            }
                        }
                    }
                }
                
                await this.db.commit();
                
            } catch (error) {
                await this.db.rollback();
                throw error;
            }
        } catch (error) {
            logger.error(`Error logging result for monitor ${monitorId}:`, error);
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
        var statusName = "UP",
        statusIcon = "🟢";
        if(status == 0) {
            statusName = "DOWN",
            statusIcon = "🔴";
        }
        try {
            await axios.post(monitor.webhook_url, {
                username: "Uptime Bot",
                icon_url: "https://uptime.jointlystudios.com/images/uptime-logo.png",
                text: statusIcon + " " + monitor.name + " is " + statusName
            });
        } catch (error) {
            logger.error(`Error sending webhook for monitor ${monitor.id}:`, error);
        }
    }
}

module.exports = UptimeMonitor;