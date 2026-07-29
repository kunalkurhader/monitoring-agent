package com.kunalkurhade.agent.model;

import java.time.LocalDateTime;

public class SystemStats {
    public double cpuUsage;
    public long totalMemory;
    public long freeMemory;
    public LocalDateTime createdAt = LocalDateTime.now();

    public SystemStats(double cpuUsage, long totalMemory, long freeMemory) {
        this.cpuUsage = cpuUsage;
        this.totalMemory = totalMemory;
        this.freeMemory = freeMemory;
    }
}
