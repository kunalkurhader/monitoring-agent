package com.kunalkurhade.agent.collectors;

import com.kunalkurhade.agent.model.DiskStats;
import oshi.SystemInfo;
import oshi.software.os.OSFileStore;

import java.util.List;

public class DiskStatsCollector {

    private DiskStatsCollector() {}

    public static List<DiskStats> collect() {
        return new SystemInfo()
                .getOperatingSystem()
                .getFileSystem()
                .getFileStores(false)
                .stream()
                .filter(store -> store.getTotalSpace() > 0)
                .map(DiskStatsCollector::toDiskStats)
                .toList();
    }

    private static DiskStats toDiskStats(OSFileStore store) {
        return new DiskStats(
                store.getVolume(),
                store.getMount(),
                store.getType(),
                store.getTotalSpace(),
                store.getFreeSpace()
        );
    }
}
