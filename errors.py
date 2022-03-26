# IPP project 2. part
# Author: Vojtech Dvorak (xdvora3o)

"""Contains error variables with corresponding error codes
class 'Error' with static methods, that are used to printing errors and
exiting wiht specific error code. Also contains specific exception classes.
"""

import sys

EXIT_SUCCESS = 0

ARGUMENT_ERROR = 10
INPUT_FILE_ERROR = 11
OUPUT_FILE_ERROR = 12

NOT_WELLFORMED = 31
BAD_XML = 32

SEMANTIC_ERROR = 52
BAD_TYPES = 53
VAR_NOT_EXISTS = 54
FRAME_NOT_EXISTS = 55
MISSING_VALUE = 56
BAD_VALUE = 57
INVALID_STRING_OP = 58

INTERNAL_ERROR = 99

class Error:
    """Contains static method for error handling and exception subclasses"""

    @staticmethod
    def printGeneral(e : Exception):
        """Prints information about genral exception, that was raised

            Args:
                e (Exception): exception object containg information about exc.
        """

        __class__.print("Unexpected exception: "+str(e))
        
        if e.__traceback__.tb_next: 
            print("\t("+str(e.__traceback__.tb_next.tb_frame.f_code.co_filename)+", line "+str(e.__traceback__.tb_next.tb_lineno)+")")

    @staticmethod
    def print(msg: str):
        """Prints error msg in specific format"""

        print("\033[1;31mChyba: \033[0m"+msg, file=sys.stderr)

    @staticmethod
    def exit(errCode: int, msg: str):
        """Prints error msg in specific format and ends script with given 
        return code
        """

        __class__.print(msg)
        sys.exit(errCode)


    class MException(Exception):
        """Modified exception class used for better error handling and
        distinguishing errors

        Args:
            code (int): error code that specifies exception
            msg (str): error msg
        """

        def __init__(self, code : int, msg : str = None):
            self.code = code
            self.msg = msg if msg != None else str(code) + "!"
            self.prefix = "Chyba:"

        def print(self):
            print("\033[0;31m"+self.prefix+" \033[0m"+self.msg, file=sys.stderr)

        def getCode(self):
            return self.code

        def exit(self):
            sys.exit(self.code)


    class ARGError(MException):
        """MException subclass used for handling errors caused by bad call 
        of script
        """

        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Chyba parametrů:"

    class XMLError(MException):
        """MException subclass used for handling errors caused by bad XML source
        (e.g. unknown opcode, not-wellformed xml...)
        """

        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Chyba XML vstupu:"

    class SemanticError(MException):
        """MException subclass used for handling semantic errors such as bad 
        combination of instruction and operand types, redefinition of
        labels and so on
        """
        
        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Sémantická chyba:"

    class RuntimeError(MException):
        """MException subclass used for handling runtime errors, by giving
        specific program context you can achieve more detailed error msg

        Args:
            code (int): error code that specifies exception
            msg (str): error msg
            ctx (ProgramCtx): context of program, that raised exception
        """

        def __init__(self, code: int, msg: str = None, ctx = None):
            super().__init__(code, msg)
            self.prefix = "Běhová chyba ("+str(code)+"):"
            self.ctx = ctx

        def print(self):
            failed = self.ctx.getInstruction() if self.ctx else None
            suffix = "\033[1m("+str(failed)+")\033[0m" if failed else ""
            print("\033[0;31m"+self.prefix+" \033[0m"+self.msg+" "+suffix, file=sys.stderr)
    
    class InternalError(MException):
        """MException subclass for internal errors
        """

        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Interní chyba programu:"

        def print(self):
            print("\033[1;31m"+self.prefix+" \033[0m"+self.msg, file=sys.stderr)

