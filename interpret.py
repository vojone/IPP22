import getopt
import sys
import instructions
from iparser import IParser

from program import ProgramContext
from os import F_OK, R_OK, access
from errors import *


def printHelp():
    print("IPPcode22 interpret")


class argumentParser:
    SHORT_O = ""
    LONG_O = ["help", "source=", "input="]


    @staticmethod
    def checkFile(path):
        return access(path, F_OK) and access(path, R_OK)


    @staticmethod
    def parseArgs(argv):
        '''Parses arguments from cmd line given in parameter'''

        iconfig = {}

        try:
            opts, _ = getopt.getopt(argv[1:], argumentParser.SHORT_O, argumentParser.LONG_O)
        except getopt.GetoptError as error:
            print(error)
            sys.exit(ARGUMENT_ERROR)

        for opt, val in opts:
            if opt in ["--source"]:
                iconfig["source"] = val
            elif opt in ["--input"]:
                iconfig["input"] = val
            elif opt in ["--help"]:
                iconfig["help"] = True
            else:
                sys.exit(ARGUMENT_ERROR)
        
        return iconfig


    @staticmethod
    def checkConfig(configDict):
        '''Checks validity of config file returned from parseArgs function'''

        if "help" in configDict:
            printHelp()
            if len(configDict) > 1:
                sys.exit(ARGUMENT_ERROR)
            else:
                sys.exit(EXIT_SUCCESS)

        if "input" in configDict:
            if not argumentParser.checkFile(configDict["input"]):
                print("Cannot open input file for reading!")
                sys.exit(INPUT_FILE_ERROR)
            else:
                configDict["inputOpened"] = open(configDict["input"])

        if "source" in configDict:
            if not argumentParser.checkFile(configDict["source"]):
                raiseError(INPUT_FILE_ERROR, "Cannot open source file for reading!")
            else:
                configDict["sourceOpened"] = open(configDict["source"]) 

        if "input" not in configDict and "source" not in configDict:
            raiseError(ARGUMENT_ERROR, "Missing parameters! There must be at least one of the parameters --source='' or --input=''")

        elif "input" not in configDict:
            configDict["inputOpened"] = sys.stdin
        elif "source" not in configDict:
            configDict["sourceOpened"] = sys.stdin



config = argumentParser.parseArgs(sys.argv)
argumentParser.checkConfig(config)

interpretParser = IParser(config)
program = interpretParser.parse()

ctx = ProgramContext()

instructions.instDict["DEFVAR"][instructions.FUNCTION_INDEX](ctx, "GF@a")

print(ctx.globalFrame)
