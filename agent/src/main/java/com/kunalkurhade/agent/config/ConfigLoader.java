package com.kunalkurhade.agent.config;

import com.kunalkurhade.agent.config.DbConfig;
import com.kunalkurhade.agent.config.AgentPaths;
import java.io.FileInputStream;
import java.util.Arrays;
import java.util.List;
import java.util.Properties;

public class ConfigLoader {

    public static DbConfig loadDbConfig() throws Exception {
        Properties p = loadProperties();

        return new DbConfig(
            p.getProperty("db.host"),
            Integer.parseInt(p.getProperty("db.port")),
            p.getProperty("db.name"),
            p.getProperty("db.user"),
            p.getProperty("db.password")
        );
    }

    public static Properties loadProperties() throws Exception {
        Properties props = new Properties();
        try (FileInputStream in = new FileInputStream(AgentPaths.BASE_DIR)) {
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
