const mysql = require('mysql2/promise');
const config = require('./utils/config');
const { logger } = require('./utils/logger');

async function dailyRollover() {
    let connection;
    
    try {
        logger.info('Starting daily uptime data rollover');
        
        // Create database connection
        connection = await mysql.createConnection({
            host: config.db.host,
            user: config.db.user,
            password: config.db.password,
            database: config.db.name
        });
        
        // Begin transaction
        await connection.beginTransaction();
        
        // Save current daily uptime percentages to daily_uptime table
        await connection.execute(`
            INSERT INTO daily_uptime (monitor_id, date, uptime_percentage)
            SELECT monitor_id, CURRENT_DATE(), daily_uptime_percentage
            FROM monitor_status
        `);
        
        // Reset daily counters
        await connection.execute(`
            UPDATE monitor_status
            SET todays_checks = 0,
                todays_successful_checks = 0,
                daily_uptime_percentage = 100.0
        `);
        
        // Delete data older than 90 days
        await connection.execute(`
            DELETE FROM daily_uptime
            WHERE date < DATE_SUB(CURRENT_DATE(), INTERVAL 90 DAY)
        `);
        
        // Commit transaction
        await connection.commit();
        
        logger.info('Daily uptime data rollover completed successfully');
    } catch (error) {
        if (connection) {
            await connection.rollback();
        }
        
        logger.error('Error during daily uptime data rollover:', error);
    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

// Run the function
dailyRollover();