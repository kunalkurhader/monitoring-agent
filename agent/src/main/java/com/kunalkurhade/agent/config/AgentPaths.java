package com.kunalkurhade.agent.config;

public final class AgentPaths {

    private AgentPaths() {} // prevent instantiation

    public static final String BASE_DIR =
        System.getProperty("user.home") + "/.monitoring-agent";

    public static final String CONFIG_FILE =
        BASE_DIR + "/agent.properties";
}
