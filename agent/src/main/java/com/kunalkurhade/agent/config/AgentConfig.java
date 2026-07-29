package com.kunalkurhade.agent.config;

import java.util.Properties;

public class AgentConfig {

    public int intervalSeconds;

    public AgentConfig() {}

    public AgentConfig(int intervalSeconds) {
        this.intervalSeconds = intervalSeconds;
    }

    public static AgentConfig loadFromConfig() throws Exception {
        Properties p = ConfigLoader.loadProperties();

        AgentConfig cfg = new AgentConfig();
        cfg.intervalSeconds =
                Integer.parseInt(p.getProperty("interval.seconds", "5"));

        return cfg;
    }
}
