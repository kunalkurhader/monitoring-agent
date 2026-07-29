package com.kunalkurhade.agent.model;

public class ProcessStats {

    public int pid;
    public String processName;
    public String command;
    public String userName;
    public double cpuUsage;
    public long memoryBytes;
    public String state;
    public long startTime;

    public ProcessStats(
            int pid,
            String processName,
            String command,
            String userName,
            double cpuUsage,
            long memoryBytes,
            String state,
            long startTime
    ) {
        this.pid = pid;
        this.processName = processName;
        this.command = command;
        this.userName = userName;
        this.cpuUsage = cpuUsage;
        this.memoryBytes = memoryBytes;
        this.state = state;
        this.startTime = startTime;
    }
}
