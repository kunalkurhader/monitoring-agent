package com.kunalkurhade.agent.collectors;

import com.kunalkurhade.agent.model.SystemStats;
import oshi.SystemInfo;
import oshi.hardware.CentralProcessor;
import oshi.hardware.GlobalMemory;
import oshi.hardware.HardwareAbstractionLayer;

public class SystemStatsCollector {

    private static long[] prevTicks =
            new long[CentralProcessor.TickType.values().length];

    public static SystemStats collect() {

        SystemInfo si = new SystemInfo();
        HardwareAbstractionLayer hw = si.getHardware();

        CentralProcessor cpu = hw.getProcessor();
        GlobalMemory mem = hw.getMemory();

        double cpuUsage =
                cpu.getSystemCpuLoadBetweenTicks(prevTicks) * 100;
        prevTicks = cpu.getSystemCpuLoadTicks();

        long totalMemory = mem.getTotal();
        long freeMemory = mem.getAvailable();

        return new SystemStats(cpuUsage, totalMemory, freeMemory);
    }
}
