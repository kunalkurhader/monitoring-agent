package com.kunalkurhade.agent.config;

import java.io.FileInputStream;
import java.util.Arrays;
import java.util.List;
import java.util.Properties;

public class ConfigLoader {

    public static Properties loadProperties() throws Exception {
        Properties props = new Properties();
        try (FileInputStream in = new FileInputStream(AgentPaths.CONFIG_FILE)) {
            props.load(in);
        }
        return props;
    }

    public static List<String> loadProcessFilter() throws Exception {
        Properties props = loadProperties();

        String raw = props.getProperty("process.filter", "");

        return Arrays.stream(raw.split(","))
                .map(String::trim)
                .filter(s -> !s.isEmpty())
                .toList();
    }

    public static int loadIntervalSeconds() throws Exception {
        Properties props = loadProperties();

        return Integer.parseInt(props.getProperty("interval.seconds", "5"));
    }
}
