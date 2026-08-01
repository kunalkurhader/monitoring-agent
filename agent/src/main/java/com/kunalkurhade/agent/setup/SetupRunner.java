package com.kunalkurhade.agent.setup;

import com.kunalkurhade.agent.api.ApiClient;
import com.kunalkurhade.agent.config.ConfigWriter;
import com.kunalkurhade.agent.scheduler.MonitorScheduler;
import com.kunalkurhade.agent.config.AgentConfig;
import java.net.InetAddress;
import java.util.UUID;

public class SetupRunner {

    public static void run(
            String apiUrl,
            String apiToken,
            String hostname,
            int interval,
            String filterRaw
    ) {
        try {
            String resolvedHostname = hostname == null || hostname.isBlank()
                    ? InetAddress.getLocalHost().getHostName()
                    : hostname;
            String agentId = UUID.randomUUID().toString();
            AgentConfig config = new AgentConfig(
                    apiUrl, apiToken, agentId, resolvedHostname, interval
            );

            new ApiClient(config).ping();
            ConfigWriter.save(
                    apiUrl, apiToken, agentId, resolvedHostname, interval, filterRaw
            );

            MonitorScheduler.start(config);

            System.out.println("✅ Setup completed successfully");

        } catch (Exception e) {
            System.err.println("❌ Setup failed: " + e.getMessage());
            System.exit(1);
        }
    }
}
