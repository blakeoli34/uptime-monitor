const winston = require('winston');
const path = require('path');
const config = require('./config');

const logLevels = {
    levels: {
        debug: 0,
        info: 1,
        warning: 2,
        error: 3,
        critical: 4
    },
    colors: {
        debug: 'gray',
        info: 'green',
        warning: 'yellow',
        error: 'red',
        critical: 'red'
    }
};

// Create the logger instance
const logger = winston.createLogger({
    levels: logLevels.levels,
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        // Write to daily rotating file
        new winston.transports.File({
            filename: path.join(config.logging.path, 'monitor.log'),
            maxsize: 5242880, // 5MB
            maxFiles: 30,
            tailable: true,
            format: winston.format.combine(
                winston.format.timestamp(),
                winston.format.json()
            )
        }),
        // Write errors to separate file
        new winston.transports.File({
            filename: path.join(config.logging.path, 'monitor-error.log'),
            level: 'error',
            maxsize: 5242880, // 5MB
            maxFiles: 30,
            tailable: true
        })
    ]
});

// Add console output in development
if (process.env.NODE_ENV !== 'production') {
    logger.add(new winston.transports.Console({
        format: winston.format.combine(
            winston.format.colorize(),
            winston.format.simple()
        )
    }));
}

// Add request context middleware
const addRequestContext = (req, res, next) => {
    req.log = {
        debug: (message, meta = {}) => {
            logger.debug(message, { ...meta, reqId: req.id });
        },
        info: (message, meta = {}) => {
            logger.info(message, { ...meta, reqId: req.id });
        },
        warning: (message, meta = {}) => {
            logger.warning(message, { ...meta, reqId: req.id });
        },
        error: (message, meta = {}) => {
            logger.error(message, { ...meta, reqId: req.id });
        },
        critical: (message, meta = {}) => {
            logger.critical(message, { ...meta, reqId: req.id });
        }
    };
    next();
};

module.exports = {
    logger,
    addRequestContext,
    // Helper functions for monitor-specific logging
    monitorStart: (monitorId, type) => {
        logger.info('Monitor started', { monitorId, type });
    },
    monitorStop: (monitorId) => {
        logger.info('Monitor stopped', { monitorId });
    },
    monitorCheck: (monitorId, status, responseTime, error = null) => {
        const meta = {
            monitorId,
            status,
            responseTime
        };
        if (error) {
            meta.error = error;
            logger.error('Monitor check failed', meta);
        } else {
            logger.info('Monitor check completed', meta);
        }
    },
    monitorError: (monitorId, error) => {
        logger.error('Monitor error', {
            monitorId,
            error: error.message,
            stack: error.stack
        });
    }
};