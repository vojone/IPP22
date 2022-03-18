from enum import Enum, auto
from errors import *

import sys



class Data:
    class Type(Enum):
        NIL = auto()
        BOOL = auto()
        INT = auto()
        STR = auto()


    def __init__(self, type, value):
        self.type = type
        self.setValue(value)

    
    def isValCompatible(self, value):
        if value.__class__.__name__ == "NoneType": # Every type can have 'None' (nil) value
            return True

        if self.type == __class__.Type.BOOL and value.__class__ == bool:
            return True
        elif self.type == __class__.Type.INT and value.__class__ == int:
            return True
        elif self.type == __class__.Type.STR and value.__class__ == str:
            return True
        else:
            return False


    def getType(self):
        return self.type


    def setType(self, newType):
        self.type = newType


    def getValue(self):
        return self.value


    def setValue(self, newValue):
        if self.isValCompatible(newValue):
            self.value = newValue
        else:
            raise Error.InternalError(INTERNAL_ERROR, "Unable to assign value of type "+newValue.__class__.__name__+" to data of type "+self.type.name+"!")



class Operand:
    class Type(Enum):
        VAR = auto()
        LITERAL = auto()
        LABEL = auto()
        TYPE = auto()


    def __init__(self, type : Type, content : str):
        self.type = type
        self.content = content


    def getType(self):
        return self.type


    def getContent(self):
        return self.content



class Literal(Operand):
    def __init__(self, content : str, dataType: Data.Type, value):
        super().__init__(super().Type.LITERAL, content)
        self.data = Data(dataType, value)


    def getData(self):
        return self.data



class Type(Operand):
    def __init__(self, content: str, typeVal):
        super().__init__(super().Type.TYPE, content)
        self.typeVal = typeVal

    
    def getTypeVal(self):
        return self.typeVal



class Variable(Operand):
    class Frame(Enum):
        GLOBAL = auto()
        LOCAL = auto()
        TEMPORARY = auto()


    def __init__(self, content : str, frame : Frame, name : str):
        super().__init__(super().Type.VAR, content)
        self.frame = frame
        self.name = name


    def getFrame(self):
        return self.frame
    

    def getName(self):
        return self.name



class Stack:
    def __init__(self):
        self.elements = []

    
    def setErr(self, emptyErrMsg = None, errCode = INTERNAL_ERROR):
        self.errMsg = emptyErrMsg
        self.errCode = errCode


    def pop(self, currentOrder = None, eTol = False):
        if not self.elements and not eTol:
            raise Error.RuntimeError(self.errCode, self.errMsg, currentOrder)
        elif not self.elements and eTol:
            return
        else:
            return self.elements.pop()


    def push(self, element):
        self.elements.append(element)


    def getTop(self, currentOrder = None, eTol = False):
        if not self.elements and not eTol:
            raise Error.RuntimeError(self.errCode, self.errMsg, currentOrder)
        elif not self.elements and eTol:
            return None
        else:
            return self.elements[-1]



class ProgramContext:
    def __init__(self, input = sys.stdin):
        self.nextInstructionIndex = None
        self.nextInstructionOrder = None
        self.input = input

        self.frames = {
            Variable.Frame.GLOBAL : {},
            Variable.Frame.LOCAL : None,
            Variable.Frame.TEMPORARY : None,
        }

        self.frameStack = Stack()
        self.frameStack.setErr("Empty FRAME stack (unable to access top/pop it)", FRAME_NOT_EXISTS)
        self.callStack = Stack()
        self.callStack.setErr("Empty CALL stack! (unable to access top/pop it)", MISSING_VALUE)
        self.dataStack = Stack()
        self.dataStack.setErr("Empty DATA stack! (unable to access top/pop it)", MISSING_VALUE)

        self.labelMap = {}

    
    def setNextInstructionIndex(self, index :int):
        self.nextInstructionIndex = index
    

    def getNextInstructionIndex(self) -> int:
        return self.nextInstructionIndex


    def setNextInstructionOrder(self, order : int):
        self.nextInstructionOrder = order
    

    def getNextInstructionOrder(self) -> int:
        return self.nextInstructionOrder


    def clearLabelMap(self):
        self.labelMap = {}


    def getLabelMap(self) -> dict:
        return self.labelMap


    def addLabel(self, name : str, targetIndex : int):
        if name in self.labelMap:
            raise Error.SemanticError(SEMANTIC_ERROR, "Redefinition of label "+name+"!")
        else:
            self.labelMap[name] = targetIndex


    def getLabelIndex(self, name : str) -> int:
        if name in self.labelMap:
            raise Error.SemanticError(SEMANTIC_ERROR, "Undefined label "+name+"!")
        else:
            return self.labelMap[name]


    def getFrame(self, frameMark : Variable.Frame) -> dict:
        if not frameMark in self.frames or self.frames[frameMark] == None:
            raise Error.RuntimeError(FRAME_NOT_EXISTS, "Frame '"+frameMark.name+"' does not exists!", self.getNextInstructionOrder())
        else:
            return self.frames[frameMark]


    def addVar(self, var : Variable):
        name = var.getName()
        frame = self.getFrame(var.getFrame())

        if var in frame:
            raise Error.RuntimeError(SEMANTIC_ERROR, "Redefinition of variable "+name+" in frame "+var.getFrame().name, self.getNextInstructionOrder())
        else:
            frame[name] = None


    def checkVar(self, var : Variable, canBeUnInit = False):
        name = var.getName()
        frame = self.getFrame(var.getFrame())
        if not name in frame:
            raise Error.RuntimeError(VAR_NOT_EXISTS, "Variable '"+name+"' does not exists in frame "+var.getFrame().name+"!", self.getNextInstructionOrder())
        elif frame[name] == None and not canBeUnInit:
            raise Error.RuntimeError(MISSING_VALUE, "Missing value of '"+name+"' in frame "+var.getFrame().name+"!", self.getNextInstructionOrder())
        else:
            return frame, name


    def getVar(self, var : Variable):
        frame, name = self.checkVar(var)
        return frame[name]


    def setVar(self, varObj : Variable, newData : Data):
        frame, name = self.checkVar(varObj, canBeUnInit=True) # Check if variable exists in frame
        frame[name] = newData # Assigning new data to it
    

    
    def getData(self, operand : Operand) -> Data:
        if operand.getType() == Operand.Type.LITERAL:
            return operand.getData()
        elif operand.getType() == Operand.Type.VAR:
            return self.getVar(operand)
        else:
            raise Error.InternalError(INTERNAL_ERROR, "Unable to get data of operand of type "+operand.getType().name+"!")

    
    def newTempFrame(self):
        self.frames[Variable.Frame.TEMPORARY] = {}


    def clearTempFrame(self):
        self.frames[Variable.Frame.TEMPORARY] = None


    def updateLocalFrame(self):
        self.frames[Variable.Frame.LOCAL] = self.frameStack.getTop(eTol=True)  



class Instruction:
    def __init__(self, opcode : str, order : int, operands):
        self.opcode = opcode
        self.order = order
        self.operands = operands
    

    def getOpCode(self):
        return self.opcode


    def getOrder(self):
        return self.order


    def getOperands(self):
        return self.operands



class Executable(Instruction):
    def __init__(self, opcode: str, order: int, operands, action):
        super().__init__(opcode, order, operands)
        self.action = action


    def do(self, ctx : ProgramContext):
        ctx.setNextInstructionOrder(self.order)
        self.action(ctx, self.operands)



class Label(Instruction):
    def do(self, ctx : ProgramContext):
        pass


class Program:
    def __init__(self, input = sys.stdin, instructions = []):
        self.ctx = ProgramContext(input)

        self.instructions = []
        for i in instructions:
            self.addInstruction(i)


    def mapLabels(self):
        for index, i in enumerate(self.instructions):
            if i.__class__ == Label:
                self.ctx.addLabel(i.operands[0].getContent(), index)


    def addInstruction(self, instruction : Instruction):
        if len(self.instructions) == 0:
            self.instructions.append(instruction)
        else:
            pos = len(self.instructions)
            while instruction.getOrder() < self.instructions[pos-1].getOrder():
                pos -= 1
                if pos == 0:
                    break

            self.instructions.insert(pos, instruction)


    def getInstructions(self):
        return self.instructions


    def start(self):
        self.ctx.setNextInstructionIndex(0)


    def finish(self):
        self.ctx.setNextInstructionOrder(None)
        self.ctx.setNextInstructionIndex(None)


    def nextInstruction(self):
        nextInstructionIndex = self.ctx.getNextInstructionIndex()
        
        if nextInstructionIndex == None:
            pass
        else:
            self.ctx.setNextInstructionIndex(1 + nextInstructionIndex)


    def reset(self):
        if self.instructions:
            self.start()
        else:
            self.finish()


    def hasEnded(self):
        return self.ctx.nextInstructionIndex == None


    def run(self):
        self.reset()
        while not self.hasEnded():
            curInstruction = self.instructions[self.ctx.nextInstructionIndex]
            curInstruction.do(self.ctx)

            self.nextInstruction()

            if self.ctx.nextInstructionIndex >= len(self.instructions):
                self.finish()
