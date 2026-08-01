package com.kunalkurhade.agent.scheduler;

import com.kunalkurhade.agent.collectors.SystemStatsCollector;
import com.kunalkurhade.agent.api.ApiClient;
import com.kunalkurhade.agent.model.SystemStats;
import com.kunalkurhade.agent.collectors.ProcessStatsCollector;
import com.kunalkurhade.agent.collectors.DiskStatsCollector;
import com.kunalkurhade.agent.model.ProcessStats;
import com.kunalkurhade.agent.config.AgentConfig;

import java.util.List;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class MonitorScheduler {

    public static void start(AgentConfig config) {

        ApiClient apiClient = new ApiClient(config);

        ScheduledExecutorService scheduler =
                Executors.newSingleThreadScheduledExecutor();

        scheduler.scheduleAtFixedRate(() -> {
            try {
                SystemStats stats = SystemStatsCollector.collect();
                List<ProcessStats> processes = ProcessStatsCollector.collectFiltered();
                if (processes.size() > 500) {
                    processes = processes.subList(0, 500);
                }
                apiClient.sendMetrics(stats, processes);
            } catch (Exception e) {
                e.printStackTrace();
            }

            try {
                apiClient.sendDiskMetrics(DiskStatsCollector.collect());
            } catch (Exception e) {
                e.printStackTrace();
            }

        }, 0, config.intervalSeconds, TimeUnit.SECONDS);

        System.out.println(
            "Monitoring started every " + config.intervalSeconds
                + " seconds for " + config.hostname
                + "; CPU, RAM, processes, and disk usage use the same interval"
        );
    }
}
