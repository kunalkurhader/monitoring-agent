package com.kunalkurhade.agent.collectors;

import com.kunalkurhade.agent.config.ConfigLoader;
import com.kunalkurhade.agent.model.ProcessStats;
import oshi.SystemInfo;
import oshi.software.os.OperatingSystem;
import oshi.software.os.OSProcess;

import java.util.ArrayList;
import java.util.List;

public class ProcessStatsCollector {

    public static List<ProcessStats> collectAll() {

        SystemInfo si = new SystemInfo();
        OperatingSystem os = si.getOperatingSystem();

        List<OSProcess> processes =
                os.getProcesses(
                        p -> true,                               // no filter
                        OperatingSystem.ProcessSorting.CPU_DESC, // sort by CPU
                        0                                        // 0 = no limit
                );

        List<ProcessStats> list = new ArrayList<>();

        for (OSProcess p : processes) {
            list.add(new ProcessStats(
                    p.getProcessID(),
                    p.getName(),
                    p.getCommandLine(),
                    p.getUser(),
                    p.getProcessCpuLoadCumulative() * 100,
                    p.getResidentSetSize(),
                    p.getState().name(),
                    p.getStartTime()
            ));
        }

        return list;
    }

    public static List<ProcessStats> collectFiltered() throws Exception {

        SystemInfo si = new SystemInfo();
        OperatingSystem os = si.getOperatingSystem();

        List<String> filters = ConfigLoader.loadProcessFilter();

        List<OSProcess> processes =
            os.getProcesses(p -> true,
                            OperatingSystem.ProcessSorting.CPU_DESC,
                            0);

        List<ProcessStats> result = new ArrayList<>();

        for (OSProcess p : processes) {

            // 🔹 Apply CLI filter
            if (!filters.isEmpty()) {
                boolean match = filters.stream().anyMatch(f ->
                    (p.getName() != null && p.getName().toLowerCase().contains(f.toLowerCase())) ||
                    (p.getCommandLine() != null && p.getCommandLine().toLowerCase().contains(f.toLowerCase()))
                );
                if (!match) continue;
            }

            result.add(new ProcessStats(
                p.getProcessID(),
                p.getName(),
                p.getCommandLine(),
                p.getUser(),
                p.getProcessCpuLoadCumulative() * 100,
                p.getResidentSetSize(),
                p.getState().name(),
                p.getStartTime()
            ));
        }

        return result;
    }
}
