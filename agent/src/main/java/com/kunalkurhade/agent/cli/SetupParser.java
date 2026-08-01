package com.kunalkurhade.agent.cli;

import org.apache.commons.cli.*;

public class SetupParser {

    public static CommandLine parse(String[] args) throws Exception {

        Options options = new Options();

        options.addOption(Option.builder("url")
                .longOpt("api-url")
                .hasArg()
                .required()
                .build());

        options.addOption(Option.builder("token")
                .longOpt("api-token")
                .hasArg()
                .required()
                .build());

        options.addOption(Option.builder("name")
                .longOpt("hostname")
                .hasArg()
                .build());

        options.addOption(Option.builder("interval")
                .hasArg()
                .build());

        options.addOption(
                Option.builder("f")
                        .longOpt("filter")
                        .hasArg()
                        .desc("Process filter list (comma separated, e.g. java,php)")
                        .build()
        );


        CommandLineParser parser = new DefaultParser();
        return parser.parse(options, args);
    }
}
