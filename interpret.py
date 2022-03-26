# IPP project 2. part
# Author: Vojtech Dvorak (xdvora3o)

"""Contains main body of IPPcode22 interpreter and class responsible for
communicating with outside
"""

import getopt
import sys

from os import F_OK, R_OK, access
from iparser import IParser, SAnalayzer
from errors import *


def printHelp():
    """Print help information to stdout"""

    print("""Python 3.8 skript - IPPcode22 interpret
Provede kód v jazyce IPPcode22 přeložený do vstupní XML reprezentace (např.
scriptem parse.php). Při spuštění skriptu je nutné uvést alespoň jeden z
parametrů --input="" (soubor se vtupem programu) nebo --source="" (soubor
se vstupní reprezentací kódu). Výstup provádění programu je vypsán na standard-
ní chybový výstup a chybová hlášení na standardní ch. výstup.

POUŽITÍ:
python3.8 [--input="VSTUPNI_SOUBOR"] [--source="SOUBOR_S_KODEM"] [MOŽNOSTI]

MOŽNOSTI:
-

Návratové kódy:
10\tNeplatná kombinace parametrů
11\tChyba při čtení vstupního souboru
12\tChyba při čtení souboru s XML reprezentací kódu
31\tChybná syntaxe XML
32\tChybná XML reprezentace kódu (např. volání neexistrující instrukce)
52\tSémantická chyba 
53-58\tBěhové chyby
99\tInterní chyba programu
    """)



class configCreator:
    """Parses arguments from command line and checks if they are valid"""

    SHORT_O = ""
    LONG_O = ["help", "source=", "input="]


    @staticmethod
    def checkFile(path : str):
        """Checks if file exists and if it is accessible for reading"""

        if access(path, F_OK) and access(path, R_OK):
            return
        else:
            Error.exit(INPUT_FILE_ERROR, "Chyb při otvírární souboru '"+path+"' pro čtení!")


    @staticmethod
    def parseArgs(argv : list):
        """Parses arguments from cmdline given in parameter and creates config
        dictionary due to analysis
        """

        iconfig = {}
        try:
            opts, _ = getopt.getopt(argv[1:], __class__.SHORT_O, __class__.LONG_O)
        except getopt.GetoptError as error:
            Error.exit(ARGUMENT_ERROR, "Neznámý přepínač "+error.opt+" (zadejte --help pro povolené přepínače)!")

        for opt, val in opts:
            if opt in ["--source"]:
                iconfig["source"] = val
            elif opt in ["--input"]:
                iconfig["input"] = val
            elif opt in ["--help"]:
                iconfig["help"] = True
            else:
                Error.exit(ARGUMENT_ERROR, "Chybný přepínač "+opt+" !")
        
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
            Error.exit(ARGUMENT_ERROR, "Chbyějící parametry skriptu! Musí být zadanán alespoň jeden  z parametrů --source=FILE nebo --input=FILE")

    @staticmethod
    def cleanUp(config : dict):
        """Closes files and correctly frees all resources used by program"""

        if "input" in config:
            config["inputOpened"].close()
        if "source" in config:
            config["sourceOpened"].close()


# Main body of interpreter

config = configCreator.parseArgs(sys.argv) # Parsing arguments of the script
configCreator.checkConfig(config) 

interpretParser = IParser(config)
semanticChecker = SAnalayzer()

returnCode = EXIT_SUCCESS  # Imlicit return code if everything runs correctly

try:
    program = interpretParser.parse()
    semanticChecker.checkSemantics(program)
    program.run()

except Error.MException as e:
    e.print()
    returnCode = e.getCode()

except Exception as e:
    Error.printGeneral(e)
    returnCode = INTERNAL_ERROR

else:
    programReturnCode = program.getContext().getReturnCode()
    if programReturnCode != None:
        returnCode = programReturnCode

finally:
    configCreator.cleanUp(config) # Freeing resources
    sys.exit(returnCode)

