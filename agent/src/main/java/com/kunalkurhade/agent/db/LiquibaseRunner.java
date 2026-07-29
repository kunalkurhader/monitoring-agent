package com.kunalkurhade.agent.db;

import liquibase.Liquibase;
import liquibase.database.Database;
import liquibase.database.DatabaseFactory;
import liquibase.resource.ClassLoaderResourceAccessor;
import liquibase.database.jvm.JdbcConnection;

import java.sql.Connection;

public class LiquibaseRunner {

    public static void runMigrations(Connection connection) throws Exception {

        Database database = DatabaseFactory.getInstance()
                .findCorrectDatabaseImplementation(new JdbcConnection(connection));

        Liquibase liquibase = new Liquibase(
                "migrations/db.changelog-master.xml",
                new ClassLoaderResourceAccessor(),
                database
        );

        liquibase.update();
    }
}
