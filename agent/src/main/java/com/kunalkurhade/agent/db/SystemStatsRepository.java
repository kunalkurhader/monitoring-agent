package com.kunalkurhade.agent.db;

import com.kunalkurhade.agent.model.SystemStats;

import java.sql.Connection;
import java.sql.PreparedStatement;

public class SystemStatsRepository {

    public static void save(SystemStats stats) {

        String sql = " INSERT INTO system_stats (cpu_usage, total_memory, free_memory, created_at) VALUES (?, ?, ?, NOW())";

        try (Connection con = DatabaseManager.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {

            ps.setDouble(1, stats.cpuUsage);
            ps.setLong(2, stats.totalMemory);
            ps.setLong(3, stats.freeMemory);

            ps.executeUpdate();

        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
