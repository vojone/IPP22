def defVar(programCtx, *args):
    programCtx.addVar(args[0])

FUNCTION_INDEX = 0
ARGUMENT_INDEX = 1

instDict = {
    "DEFVAR" : [defVar, ["var"]],
}
