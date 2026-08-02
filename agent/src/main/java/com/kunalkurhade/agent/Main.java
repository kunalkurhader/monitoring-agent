package com.kunalkurhade.agent;

import com.kunalkurhade.agent.cli.SetupParser;
import com.kunalkurhade.agent.config.AgentConfig;
import com.kunalkurhade.agent.scheduler.MonitorScheduler;
import com.kunalkurhade.agent.setup.SetupRunner;
import org.apache.commons.cli.CommandLine;

public class Main {

    public static void main(String[] args) throws Exception {

        /* ===============================
           CASE 1: SETUP (one-time only)
           =============================== */
        if (args.length > 0 && "setup".equalsIgnoreCase(args[0])) {

            String[] setupArgs =
                    java.util.Arrays.copyOfRange(args, 1, args.length);

            CommandLine cmd = SetupParser.parse(setupArgs);

            String apiUrl = cmd.getOptionValue("url");
            String apiToken = cmd.getOptionValue("token");
            String hostname = cmd.getOptionValue("name");
            int interval = Integer.parseInt(cmd.getOptionValue("interval", "5"));
            String filterRaw = cmd.getOptionValue("f", "");
            String[] logFiles = cmd.getOptionValues("log");

            SetupRunner.run(apiUrl, apiToken, hostname, interval, filterRaw,
                    logFiles == null ? java.util.List.of() : java.util.Arrays.asList(logFiles));
            return;
        }

        /* ===============================
           CASE 2: START (normal run)
           =============================== */
        if (args.length == 0 || "start".equalsIgnoreCase(args[0])) {

            System.out.println("Starting Matrix Agent...");

            AgentConfig config = AgentConfig.loadFromConfig();
            MonitorScheduler.start(config);
            return;
        }

        /* ===============================
           CASE 3: INVALID USAGE
           =============================== */
        System.out.println("""
            Usage:
              Setup (one time):
                java -jar agent.jar setup -url https://monitoring-agent.example.com -token TOKEN -interval 5 -f java -log /var/log/app.log

              Start agent:
                java -jar agent.jar
                java -jar agent.jar start
            """);
    }
}
