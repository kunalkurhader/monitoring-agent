package com.kunalkurhade.agent.scheduler;

import com.kunalkurhade.agent.collectors.SystemStatsCollector;
import com.kunalkurhade.agent.db.SystemStatsRepository;
import com.kunalkurhade.agent.model.SystemStats;
import com.kunalkurhade.agent.collectors.ProcessStatsCollector;
import com.kunalkurhade.agent.db.ProcessStatsRepository;
import com.kunalkurhade.agent.model.ProcessStats;
import com.kunalkurhade.agent.config.AgentConfig;

import java.util.List;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class MonitorScheduler {

    public static void start(AgentConfig config) {

        ScheduledExecutorService scheduler =
                Executors.newSingleThreadScheduledExecutor();

        scheduler.scheduleAtFixedRate(() -> {
            try {
                SystemStats stats = SystemStatsCollector.collect();
                SystemStatsRepository.save(stats);

                List<ProcessStats> processes = ProcessStatsCollector.collectFiltered();
                ProcessStatsRepository.saveAll(processes);

            } catch (Exception e) {
                e.printStackTrace();
            }

        }, 0, config.intervalSeconds, TimeUnit.SECONDS);

        System.out.println(
            "Monitoring started every " + config.intervalSeconds + " seconds"
        );
    }
}
