package com.kunalkurhade.agent.config;

import java.util.Properties;

public class AgentConfig {

    public int intervalSeconds;
    public String apiUrl;
    public String apiToken;
    public String agentId;
    public String hostname;

    public AgentConfig() {}

    public AgentConfig(
            String apiUrl,
            String apiToken,
            String agentId,
            String hostname,
            int intervalSeconds
    ) {
        this.apiUrl = apiUrl;
        this.apiToken = apiToken;
        this.agentId = agentId;
        this.hostname = hostname;
        this.intervalSeconds = intervalSeconds;
    }

    public static AgentConfig loadFromConfig() throws Exception {
        Properties p = ConfigLoader.loadProperties();

        AgentConfig cfg = new AgentConfig();
        cfg.apiUrl = required(p, "api.url");
        cfg.apiToken = required(p, "api.token");
        cfg.agentId = required(p, "agent.id");
        cfg.hostname = required(p, "agent.hostname");
        cfg.intervalSeconds =
                Integer.parseInt(p.getProperty("interval.seconds", "5"));

        return cfg;
    }

    private static String required(Properties properties, String key) {
        String value = properties.getProperty(key);
        if (value == null || value.isBlank()) {
            throw new IllegalStateException("Missing agent configuration: " + key);
        }
        return value;
    }
}
