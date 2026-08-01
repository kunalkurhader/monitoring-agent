package com.kunalkurhade.agent.model;

public class DiskStats {
    public String device;
    public String mountPoint;
    public String fileSystemType;
    public long totalBytes;
    public long freeBytes;
    public long usedBytes;

    public DiskStats(
            String device,
            String mountPoint,
            String fileSystemType,
            long totalBytes,
            long freeBytes
    ) {
        this.device = device;
        this.mountPoint = mountPoint;
        this.fileSystemType = fileSystemType;
        this.totalBytes = totalBytes;
        this.freeBytes = freeBytes;
        this.usedBytes = Math.max(0, totalBytes - freeBytes);
    }
}
