import getopt
import sys

from os import F_OK, R_OK, access
from iparser import IParser, SAnalayzer
from errors import *


def printHelp():
    """Print help information to stdout"""

    print("""IPPcode22 interpreter
abc
    """)



class argumentParser:
    """Parses arguments from command line and checks if they are valid"""

    SHORT_O = ""
    LONG_O = ["help", "source=", "input="]


    @staticmethod
    def checkFile(path):
        """Checks if file exists and if it is accessible for reading"""

        if access(path, F_OK) and access(path, R_OK):
            return
        else:
            Error.exit(INPUT_FILE_ERROR, "Unable to open file '"+path+"' for reading!")


    @staticmethod
    def parseArgs(argv):
        """Parses arguments from cmd line given in parameter"""

        iconfig = {}
        try:
            opts, _ = getopt.getopt(argv[1:], __class__.SHORT_O, __class__.LONG_O)
        except getopt.GetoptError as error:
            Error.exit(ARGUMENT_ERROR, "Unknown option "+error.opt+" (see --help for allowed options)!")

        for opt, val in opts:
            if opt in ["--source"]:
                iconfig["source"] = val
            elif opt in ["--input"]:
                iconfig["input"] = val
            elif opt in ["--help"]:
                iconfig["help"] = True
            else:
                Error.exit(ARGUMENT_ERROR, "Invalid option "+opt+" !")
        
        return iconfig


    @staticmethod
    def checkConfig(config : dict):
        """Checks validity of config file returned from parseArgs function"""

        if "help" in config:
            printHelp()
            sys.exit(ARGUMENT_ERROR) if len(config) > 1 else sys.exit(EXIT_SUCCESS)

        if "input" in config:
            __class__.checkFile(config["input"])
            config["inputOpened"] = open(config["input"])
        else:
            config["inputOpened"] = sys.stdin

        if "source" in config:
            __class__.checkFile(config["source"])
            config["sourceOpened"] = open(config["source"])
        else:
            config["sourceOpened"] = sys.stdin

        if "input" not in config and "source" not in config:
            Error.exit(ARGUMENT_ERROR, "Missing parameters! There must be at least one of the parameters --source=FILE or --input=FILE")



config = argumentParser.parseArgs(sys.argv)
argumentParser.checkConfig(config)

interpretParser = IParser(config)
semanticChecker = SAnalayzer()

try:
    program = interpretParser.parse()
    
    semanticChecker.checkSemantics(program)
    program.run()
except Error.MException as e:
    e.exit()

# TODO : Clean up - close files and etc.

