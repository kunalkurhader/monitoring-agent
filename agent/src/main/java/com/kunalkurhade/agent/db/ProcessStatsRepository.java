package com.kunalkurhade.agent.db;

import com.kunalkurhade.agent.model.ProcessStats;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.List;

public class ProcessStatsRepository {

    public static void saveAll(List<ProcessStats> processes) {

        String sql = "INSERT INTO process_stats (pid, process_name, command, user_name, cpu_usage, memory_bytes, state, start_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        try (Connection con = DatabaseManager.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {

            for (ProcessStats p : processes) {
                ps.setInt(1, p.pid);
                ps.setString(2, p.processName);
                ps.setString(3, p.command);
                ps.setString(4, p.userName);
                ps.setDouble(5, p.cpuUsage);
                ps.setLong(6, p.memoryBytes);
                ps.setString(7, p.state);
                ps.setLong(8, p.startTime);
                ps.addBatch();
            }

            ps.executeBatch();

        } catch (Exception e) {
            e.printStackTrace(); // DO NOT swallow
        }
    }
}
