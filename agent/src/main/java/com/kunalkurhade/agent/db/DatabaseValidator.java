package com.kunalkurhade.agent.db;

import com.kunalkurhade.agent.config.DbConfig;

import java.sql.Connection;
import java.sql.DriverManager;

public class DatabaseValidator {

    public static void validate(DbConfig cfg) throws Exception {
        String url = "jdbc:mysql://" + cfg.host + ":" + cfg.port + "/" + cfg.database;
        try (Connection ignored = DriverManager.getConnection(
                url, cfg.user, cfg.password
        )) {}
    }
}
