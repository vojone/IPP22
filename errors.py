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

def raiseError(errCode, msg):
    print("\033[0;31mError: \033[0m" + msg, file=sys.stderr)
    sys.exit(errCode)

