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
    @staticmethod
    def print(msg: str):
        print("\033[1;31mError: \033[0m"+msg, file=sys.stderr)

    @staticmethod
    def exit(errCode: int, msg: str):
        __class__.print(msg)
        sys.exit(errCode)


    class MException(Exception):
        def __init__(self, code : int, msg : str = None):
            self.code = code
            self.msg = msg if msg != None else str(code) + "!"
            self.prefix = "Error:"

        def print(self):
            print("\033[0;31m"+self.prefix+" \033[0m"+self.msg, file=sys.stderr)

        def getCode(self):
            return self.code

        def exit(self):
            sys.exit(self.code)

    class ARGError(MException):
        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Argument error:"

    class XMLError(MException):
        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Error in XML source:"

    class SemanticError(MException):
        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Semantic error:"

    class RuntimeError(MException):
        def __init__(self, code: int, msg: str = None, iOrder : int = None):
            super().__init__(code, msg)
            self.prefix = "Runtime error ("+str(code)+"):"
            self.iOrderStr = "" if iOrder == None else str(iOrder)

        def print(self):
            print("\033[0;31m"+self.prefix+" \033[0m"+self.msg+" (at order: \033[1;33m"+self.iOrderStr+"\033[0m)", file=sys.stderr)
    
    class InternalError(MException):
        def __init__(self, code: int, msg: str = None):
            super().__init__(code, msg)
            self.prefix = "Internal error:"

        def print(self):
            print("\033[1;31m"+self.prefix+" \033[0m"+self.msg, file=sys.stderr)

