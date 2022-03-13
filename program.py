import sys

from numpy import safe_eval
from errors import *

class ProgramContext:
    GF_MARK = "GF"
    TF_MARK = "TF"
    LF_MARK = "LF"

    def __init__(self):
        self.frameStack = []
        self.globalFrame = {}
        self.localFrame = None
        self.tempFrame = None

    def safeVarAdd(frame, varName):
        if varName not in frame:
            frame[varName] = {'type' : None, 'value' : None}
        else:
            sys.exit(SEMANTIC_ERROR)

    def addVar(self, varWithFrame):
        frame, var = varWithFrame.split('@')
        
        if frame == ProgramContext.GF_MARK:
            ProgramContext.safeVarAdd(self.globalFrame, var)
        elif frame == ProgramContext.TF_MARK:
            ProgramContext.safeVarAdd(self.localFrame, var)
        elif frame == ProgramContext.LF_MARK:
            ProgramContext.safeVarAdd(self.localFrame, var)
        else:
            pass # Internal error


