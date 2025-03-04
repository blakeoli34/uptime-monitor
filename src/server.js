const express = require('express');
const bodyParser = require('body-parser');
const UptimeMonitor = require('./monitor');
const config = require('./utils/config');
const { logger, addRequestContext } = require('./utils/logger');

const { apiLimiter, authLimiter } = require('./middleware/rateLimit');

const app = express();

// Apply rate limiting to all API routes
app.use('/api/', apiLimiter);

// Middleware
app.use(bodyParser.json());
app.use(addRequestContext);

// Initialize the monitor
const monitor = new UptimeMonitor();

// Error handling middleware
app.use((err, req, res, next) => {
    logger.error('Express error:', { 
        error: err.message,
        stack: err.stack,
        path: req.path,
        method: req.method
    });
    
    res.status(500).json({
        status: 'error',
        message: config.app.debug ? err.message : 'Internal server error'
    });
});

// Get monitor status endpoint
app.get('/api/monitors/:id/status', async (req, res) => {
    try {
        const monitorId = req.params.id;
        const [rows] = await monitor.db.execute(
            'SELECT current_status, last_check_time, last_response_time, last_error_message FROM monitor_status WHERE monitor_id = ?',
            [monitorId]
        );

        if (rows.length === 0) {
            return res.json({
                status: 'unknown',
                message: 'No monitoring data available'
            });
        }

        res.json({
            status: 'success',
            data: {
                status: rows[0].current_status,
                checked_at: rows[0].last_check_time,
                response_time: rows[0].last_response_time,
                error_message: rows[0].last_error_message
            }
        });
    } catch (error) {
        logger.error('Failed to get monitor status', {
            monitorId: req.params.id,
            error: error.message
        });
        res.status(500).json({
            status: 'error',
            message: error.message
        });
    }
});

// Get monitor statistics endpoint
app.get('/api/monitors/:id/stats', async (req, res) => {
    try {
        const monitorId = req.params.id;
        
        // Get today's stats from monitor_status
        const [todayStats] = await monitor.db.execute(
            `SELECT 
                todays_checks as total_checks,
                todays_successful_checks as successful_checks,
                daily_uptime_percentage as uptime_percentage,
                last_response_time as last_response_time
            FROM monitor_status 
            WHERE monitor_id = ?`,
            [monitorId]
        );
        
        // Get past 24 hour stats from daily_uptime
        const [historyStats] = await monitor.db.execute(
            `SELECT AVG(uptime_percentage) as avg_uptime
            FROM daily_uptime
            WHERE monitor_id = ? 
            AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)`,
            [monitorId]
        );

        res.json({
            status: 'success',
            data: {
                total_checks: todayStats.length > 0 ? todayStats[0].total_checks : 0,
                successful_checks: todayStats.length > 0 ? todayStats[0].successful_checks : 0,
                uptime_percentage: todayStats.length > 0 ? todayStats[0].uptime_percentage : 100,
                avg_response_time: todayStats.length > 0 ? todayStats[0].last_response_time : null,
                avg_uptime_24h: historyStats[0].avg_uptime || 100
            }
        });
    } catch (error) {
        logger.error('Failed to get monitor stats', {
            monitorId: req.params.id,
            error: error.message
        });
        res.status(500).json({
            status: 'error',
            message: error.message
        });
    }
});

// Start monitor
app.post('/api/monitors/start', async (req, res) => {
    try {
        const { monitorId } = req.body;
        if (!monitorId) {
            return res.status(400).json({
                status: 'error',
                message: 'Monitor ID is required'
            });
        }

        await monitor.startMonitor(monitorId);
        logger.info('Monitor started', { monitorId });
        
        res.json({
            status: 'success',
            message: 'Monitor started successfully'
        });
    } catch (error) {
        logger.error('Failed to start monitor', {
            monitorId: req.body.monitorId,
            error: error.message
        });
        
        res.status(500).json({
            status: 'error',
            message: error.message
        });
    }
});

// Stop monitor
app.post('/api/monitors/stop', async (req, res) => {
    try {
        const { monitorId } = req.body;
        if (!monitorId) {
            return res.status(400).json({
                status: 'error',
                message: 'Monitor ID is required'
            });
        }

        monitor.stopMonitor(monitorId);
        logger.info('Monitor stopped', { monitorId });

        res.json({
            status: 'success',
            message: 'Monitor stopped successfully'
        });
    } catch (error) {
        logger.error('Failed to stop monitor', {
            monitorId: req.body.monitorId,
            error: error.message
        });
        res.status(500).json({
            status: 'error',
            message: error.message
        });
    }
});

// Health check endpoint
app.get('/api/health', (req, res) => {
    res.json({
        status: 'healthy',
        environment: config.app.env,
        monitors: monitor.monitors.size
    });
});

// Initialize and start the server
async function startServer() {
    try {
        await monitor.initialize();
        
        // Start all active monitors from the database
        const [rows] = await monitor.db.execute('SELECT id FROM monitors');
        logger.info(`Starting ${rows.length} monitors`);
        for (const row of rows) {
            try {
                await monitor.startMonitor(row.id);
                logger.info(`Started monitor ${row.id}`);
            } catch (error) {
                logger.error(`Failed to start monitor ${row.id}:`, error);
            }
        }

        const port = config.monitor.port;
        app.listen(port, () => {
            logger.info(`Server running on port ${port}`, {
                environment: config.app.env,
                debug: config.app.debug
            });
        });
    } catch (error) {
        logger.critical('Failed to start server:', {
            error: error.message,
            stack: error.stack
        });
        process.exit(1);
    }
}

startServer();