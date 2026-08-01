package com.kunalkurhade.agent.config;

import java.io.File;
import java.io.FileOutputStream;
import java.util.Properties;

public class ConfigWriter {

    public static void save(
            String apiUrl,
            String apiToken,
            String agentId,
            String hostname,
            int interval,
            String filterRaw
    ) throws Exception {

        File dir = new File(AgentPaths.BASE_DIR);
        if (!dir.exists()) dir.mkdirs();

        Properties props = new Properties();
        props.setProperty("api.url", apiUrl);
        props.setProperty("api.token", apiToken);
        props.setProperty("agent.id", agentId);
        props.setProperty("agent.hostname", hostname);
        props.setProperty("interval.seconds", String.valueOf(interval));
        props.setProperty("process.filter", filterRaw);

        try (FileOutputStream out = new FileOutputStream(AgentPaths.CONFIG_FILE)) {
            props.store(out, "Agent Configuration");
        }

        File f = new File(AgentPaths.CONFIG_FILE);
        f.setReadable(false, false);
        f.setWritable(false, false);
        f.setReadable(true, true);
        f.setWritable(true, true);
    }
}
