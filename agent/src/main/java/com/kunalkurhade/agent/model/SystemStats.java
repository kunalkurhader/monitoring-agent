package com.kunalkurhade.agent.model;

public class SystemStats {
    public double cpuUsage;
    public long totalMemory;
    public long freeMemory;
    public SystemStats(double cpuUsage, long totalMemory, long freeMemory) {
        this.cpuUsage = cpuUsage;
        this.totalMemory = totalMemory;
        this.freeMemory = freeMemory;
    }
}
