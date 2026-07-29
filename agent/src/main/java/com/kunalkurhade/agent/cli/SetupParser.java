package com.kunalkurhade.agent.cli;

import org.apache.commons.cli.*;

public class SetupParser {

    public static CommandLine parse(String[] args) throws Exception {

        Options options = new Options();

        options.addOption(Option.builder("host")
                .hasArg()
                .required()
                .build());

        options.addOption(Option.builder("port")
                .hasArg()
                .build());

        options.addOption(Option.builder("db")
                .hasArg()
                .required()
                .build());

        options.addOption(Option.builder("u")
                .longOpt("user")
                .hasArg()
                .required()
                .build());

        options.addOption(Option.builder("p")
                .longOpt("password")
                .hasArg()
                .required()
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
