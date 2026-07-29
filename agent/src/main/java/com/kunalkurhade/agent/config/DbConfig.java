package com.kunalkurhade.agent.config;

public class DbConfig {
    public String host;
    public int port;
    public String database;
    public String user;
    public String password;

    public DbConfig(String host, int port, String database, String user, String password) {
        this.host = host;
        this.port = port;
        this.database = database;
        this.user = user;
        this.password = password;
    }
}
