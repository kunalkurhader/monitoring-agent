package com.kunalkurhade.agent.setup;

import com.kunalkurhade.agent.config.ConfigWriter;
import com.kunalkurhade.agent.config.DbConfig;
import com.kunalkurhade.agent.db.DatabaseValidator;
import com.kunalkurhade.agent.scheduler.MonitorScheduler;
import com.kunalkurhade.agent.config.AgentConfig;

public class SetupRunner {

    public static void run(
            String host,
            int port,
            String db,
            String user,
            String password,
            int interval,
            String filterRaw
    ) {
        try {
            DbConfig cfg = new DbConfig(host, port, db, user, password);

            DatabaseValidator.validate(cfg);

            ConfigWriter.save(host, port, db, user, password, interval, filterRaw);

            MonitorScheduler.start(new AgentConfig(interval));

            System.out.println("✅ Setup completed successfully");

        } catch (Exception e) {
            System.err.println("❌ Setup failed: " + e.getMessage());
            System.exit(1);
        }
    }
}
