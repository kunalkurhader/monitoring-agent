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

            String host = cmd.getOptionValue("host");
            int port = Integer.parseInt(cmd.getOptionValue("port", "3306"));
            String db = cmd.getOptionValue("db");
            String user = cmd.getOptionValue("u");
            String password = cmd.getOptionValue("p");
            int interval = Integer.parseInt(cmd.getOptionValue("interval", "5"));
            String filterRaw = cmd.getOptionValue("f", "");

            SetupRunner.run(host, port, db, user, password, interval, filterRaw);
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
                java -jar agent.jar setup -host localhost -port 3306 -db matrix -u root -p password -interval 5 -f java

              Start agent:
                java -jar agent.jar
                java -jar agent.jar start
            """);
    }
}
