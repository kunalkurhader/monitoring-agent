package com.kunalkurhade.agent.collectors;

import com.kunalkurhade.agent.config.AgentPaths;
import com.kunalkurhade.agent.model.LogFileChunk;

import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.RandomAccessFile;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.LinkOption;
import java.nio.file.Path;
import java.nio.file.attribute.BasicFileAttributes;
import java.security.MessageDigest;
import java.time.Instant;
import java.util.ArrayList;
import java.util.HexFormat;
import java.util.List;
import java.util.Properties;

public class LogFileCollector {
    private static final int MAX_CHUNK_BYTES = 512 * 1024;
    private final Properties offsets = new Properties();

    public LogFileCollector() {
        Path state = Path.of(AgentPaths.LOG_OFFSETS_FILE);
        if (Files.isRegularFile(state)) {
            try (FileInputStream input = new FileInputStream(state.toFile())) {
                offsets.load(input);
            } catch (Exception exception) {
                System.err.println("Unable to load log offsets: " + exception.getMessage());
            }
        }
    }

    public List<LogFileChunk> collect(List<String> configuredPaths) {
        List<LogFileChunk> chunks = new ArrayList<>();
        for (String configuredPath : configuredPaths) {
            chunks.add(collectOne(configuredPath));
        }
        return chunks;
    }

    private LogFileChunk collectOne(String configuredPath) {
        String capturedAt = Instant.now().toString();
        Path path = Path.of(configuredPath).normalize();
        String stateKey = hash(path.toString());
        try {
            if (!Files.exists(path, LinkOption.NOFOLLOW_LINKS)) {
                return descriptor(path, stateKey, "pending", capturedAt);
            }
            if (!Files.isRegularFile(path, LinkOption.NOFOLLOW_LINKS) || !Files.isReadable(path)) {
                return descriptor(path, stateKey, "unreadable", capturedAt);
            }
            BasicFileAttributes attributes = Files.readAttributes(path, BasicFileAttributes.class, LinkOption.NOFOLLOW_LINKS);
            String fileKey = String.valueOf(attributes.fileKey());
            String previousKey = offsets.getProperty(stateKey + ".file_key");
            long fileSize = attributes.size();
            long offset;
            if (previousKey == null) {
                offset = fileSize;
                saveState(stateKey, fileKey, offset);
            } else if (!previousKey.equals(fileKey)) {
                offset = 0;
            } else {
                offset = Long.parseLong(offsets.getProperty(stateKey + ".offset", "0"));
                if (fileSize < offset) offset = 0;
            }
            int bytesToRead = (int) Math.min(MAX_CHUNK_BYTES, Math.max(0, fileSize - offset));
            if (bytesToRead == 0) {
                return new LogFileChunk(path.toString(), fileKey, "ready", offset, offset, "", capturedAt, stateKey);
            }
            byte[] bytes = new byte[bytesToRead];
            int bytesRead;
            try (RandomAccessFile file = new RandomAccessFile(path.toFile(), "r")) {
                file.seek(offset);
                bytesRead = file.read(bytes);
            }
            if (bytesRead <= 0) {
                return new LogFileChunk(path.toString(), fileKey, "ready", offset, offset, "", capturedAt, stateKey);
            }
            String content = new String(bytes, 0, bytesRead, StandardCharsets.UTF_8);
            return new LogFileChunk(path.toString(), fileKey, "ready", offset, offset + bytesRead, content, capturedAt, stateKey);
        } catch (Exception exception) {
            System.err.println("Unable to read configured log " + path + ": " + exception.getMessage());
            return descriptor(path, stateKey, "unreadable", capturedAt);
        }
    }

    private LogFileChunk descriptor(Path path, String stateKey, String status, String capturedAt) {
        long offset = Long.parseLong(offsets.getProperty(stateKey + ".offset", "0"));
        return new LogFileChunk(path.toString(), offsets.getProperty(stateKey + ".file_key"), status, offset, offset, "", capturedAt, stateKey);
    }

    public void commit(List<LogFileChunk> chunks) {
        for (LogFileChunk chunk : chunks) {
            if ("ready".equals(chunk.status)) saveState(chunk.stateKey, chunk.fileKey, chunk.endOffset);
        }
    }

    private synchronized void saveState(String key, String fileKey, long offset) {
        if (fileKey != null) offsets.setProperty(key + ".file_key", fileKey);
        offsets.setProperty(key + ".offset", String.valueOf(offset));
        try {
            Files.createDirectories(Path.of(AgentPaths.BASE_DIR));
            try (FileOutputStream output = new FileOutputStream(AgentPaths.LOG_OFFSETS_FILE)) {
                offsets.store(output, "Monitoring Agent log offsets");
            }
        } catch (Exception exception) {
            throw new IllegalStateException("Unable to save log offsets", exception);
        }
    }

    private String hash(String value) {
        try {
            return HexFormat.of().formatHex(MessageDigest.getInstance("SHA-256").digest(value.getBytes(StandardCharsets.UTF_8)));
        } catch (Exception exception) {
            throw new IllegalStateException(exception);
        }
    }
}
