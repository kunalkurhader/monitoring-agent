package com.kunalkurhade.agent.model;

public class LogFileChunk {
    public String path;
    public String fileKey;
    public String status;
    public long startOffset;
    public long endOffset;
    public String content;
    public String capturedAt;
    public transient String stateKey;

    public LogFileChunk(String path, String fileKey, String status, long startOffset, long endOffset,
                        String content, String capturedAt, String stateKey) {
        this.path = path;
        this.fileKey = fileKey;
        this.status = status;
        this.startOffset = startOffset;
        this.endOffset = endOffset;
        this.content = content;
        this.capturedAt = capturedAt;
        this.stateKey = stateKey;
    }
}
