package com.kunalkurhade.agent.api;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.PropertyNamingStrategies;
import com.kunalkurhade.agent.config.AgentConfig;
import com.kunalkurhade.agent.model.ProcessStats;
import com.kunalkurhade.agent.model.SystemStats;
import com.kunalkurhade.agent.model.DiskStats;
import com.kunalkurhade.agent.model.LogFileChunk;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

public class ApiClient {

    private final AgentConfig config;
    private final HttpClient httpClient;
    private final ObjectMapper objectMapper;

    public ApiClient(AgentConfig config) {
        this.config = config;
        this.httpClient = HttpClient.newBuilder()
                .connectTimeout(Duration.ofSeconds(10))
                .build();
        this.objectMapper = new ObjectMapper()
                .setPropertyNamingStrategy(PropertyNamingStrategies.SNAKE_CASE);
    }

    public void ping() throws Exception {
        HttpRequest request = request("/api/v1/agent/ping").GET().build();
        send(request, 200);
    }

    public void sendMetrics(SystemStats systemStats, List<ProcessStats> processes) throws Exception {
        Map<String, Object> payload = new LinkedHashMap<>();
        payload.put("agent_id", config.agentId);
        payload.put("hostname", config.hostname);
        payload.put("system", systemStats);
        payload.put("processes", processes);

        HttpRequest request = request("/api/v1/agent/metrics")
                .header("Content-Type", "application/json")
                .POST(HttpRequest.BodyPublishers.ofString(objectMapper.writeValueAsString(payload)))
                .build();

        send(request, 202);
    }

    public void sendDiskMetrics(List<DiskStats> disks) throws Exception {
        Map<String, Object> payload = new LinkedHashMap<>();
        payload.put("agent_id", config.agentId);
        payload.put("hostname", config.hostname);
        payload.put("disks", disks);

        HttpRequest request = request("/api/v1/agent/disks")
                .header("Content-Type", "application/json")
                .POST(HttpRequest.BodyPublishers.ofString(objectMapper.writeValueAsString(payload)))
                .build();

        send(request, 202);
    }

    public void sendLogChunks(List<LogFileChunk> files) throws Exception {
        Map<String, Object> payload = new LinkedHashMap<>();
        payload.put("agent_id", config.agentId);
        payload.put("hostname", config.hostname);
        payload.put("files", files);
        HttpRequest request = request("/api/v1/agent/logs")
                .header("Content-Type", "application/json")
                .POST(HttpRequest.BodyPublishers.ofString(objectMapper.writeValueAsString(payload)))
                .build();
        send(request, 202);
    }

    private HttpRequest.Builder request(String path) {
        return HttpRequest.newBuilder()
                .uri(URI.create(config.apiUrl.replaceAll("/+$", "") + path))
                .timeout(Duration.ofSeconds(20))
                .header("Accept", "application/json")
                .header("Authorization", "Bearer " + config.apiToken);
    }

    private void send(HttpRequest request, int expectedStatus) throws Exception {
        HttpResponse<String> response = httpClient.send(
                request,
                HttpResponse.BodyHandlers.ofString()
        );

        if (response.statusCode() != expectedStatus) {
            throw new IllegalStateException(
                    "API returned HTTP " + response.statusCode() + ": " + response.body()
            );
        }
    }
}
