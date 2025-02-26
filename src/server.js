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

// Get monitor status
app.get('/api/monitors/:id/status', async (req, res) => {
    try {
        const monitorId = req.params.id;
        const [rows] = await monitor.db.execute(
            'SELECT * FROM monitor_logs WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 1',
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
            data: rows[0]
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

// Get monitor statistics
app.get('/api/monitors/:id/stats', async (req, res) => {
    try {
        const monitorId = req.params.id;
        const [rows] = await monitor.db.execute(
            `SELECT 
                COUNT(*) as total_checks,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as successful_checks,
                AVG(response_time) as avg_response_time,
                MIN(response_time) as min_response_time,
                MAX(response_time) as max_response_time
            FROM monitor_logs 
            WHERE monitor_id = ? 
            AND checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)`,
            [monitorId]
        );

        res.json({
            status: 'success',
            data: rows[0]
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