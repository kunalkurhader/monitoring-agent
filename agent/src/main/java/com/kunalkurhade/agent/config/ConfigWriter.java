package com.kunalkurhade.agent.config;

import com.kunalkurhade.agent.config.AgentPaths;
import java.io.File;
import java.io.FileOutputStream;
import java.util.Properties;

public class ConfigWriter {

    public static void save(
            String host,
            int port,
            String db,
            String user,
            String password,
            int interval,
            String filterRaw
    ) throws Exception {

        File dir = new File(AgentPaths.BASE_DIR);
        if (!dir.exists()) dir.mkdirs();

        Properties props = new Properties();
        props.setProperty("db.host", host);
        props.setProperty("db.port", String.valueOf(port));
        props.setProperty("db.name", db);
        props.setProperty("db.user", user);
        props.setProperty("db.password", password);
        props.setProperty("interval.seconds", String.valueOf(interval));
        props.setProperty("process.filter", filterRaw);

        try (FileOutputStream out = new FileOutputStream(AgentPaths.BASE_DIR)) {
            props.store(out, "Agent Configuration");
        }

        File f = new File(AgentPaths.BASE_DIR);
        f.setReadable(false, false);
        f.setWritable(false, false);
        f.setReadable(true, true);
        f.setWritable(true, true);
    }
}
