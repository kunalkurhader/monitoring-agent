package com.kunalkurhade.agent.db;

import com.kunalkurhade.agent.config.ConfigLoader;
import com.kunalkurhade.agent.config.DbConfig;

import java.sql.Connection;
import java.sql.DriverManager;

public class DatabaseManager {

    public static Connection getConnection() throws Exception {

        DbConfig cfg = ConfigLoader.loadDbConfig();

        String url = "jdbc:mysql://" + cfg.host + ":" + cfg.port + "/" + cfg.database;

        return DriverManager.getConnection(
            url,
            cfg.user,
            cfg.password
        );
    }
}
