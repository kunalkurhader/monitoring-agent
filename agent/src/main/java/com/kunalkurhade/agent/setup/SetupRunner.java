package com.kunalkurhade.agent.setup;

import com.kunalkurhade.agent.api.ApiClient;
import com.kunalkurhade.agent.config.ConfigWriter;
import com.kunalkurhade.agent.config.AgentConfig;
import java.net.InetAddress;
import java.util.UUID;
import java.nio.file.Path;
import java.util.List;

public class SetupRunner {

    public static void run(
            String apiUrl,
            String apiToken,
            String hostname,
            int interval,
            String filterRaw,
            List<String> logFiles
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
            List<String> normalizedLogs = logFiles.stream().map(path -> {
                Path normalized = Path.of(path).normalize();
                if (!normalized.isAbsolute()) throw new IllegalArgumentException("Log path must be absolute: " + path);
                return normalized.toString();
            }).distinct().toList();
            ConfigWriter.save(
                    apiUrl, apiToken, agentId, resolvedHostname, interval, filterRaw, normalizedLogs
            );

            System.out.println("✅ Setup completed successfully");
            System.out.println("Configured log files: " + normalizedLogs.size());

        } catch (Exception e) {
            System.err.println("❌ Setup failed: " + e.getMessage());
            System.exit(1);
        }
    }
}
