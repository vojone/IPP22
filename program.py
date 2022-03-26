# IPP project 2. part
# Author: Vojtech Dvorak (xdvora3o)

"""Contains all classes responsible for storing inner representation of analyzed
input code, for storing execution context and finally also for exectuion itself
"""

from enum import Enum, auto
from errors import *

import sys

class Data:
    """Inner representation of data during execution, it is basically composit
    of type (given by enum value) and value (it depends on type semantics)
    """

    class Type(Enum):
        NIL = auto()
        BOOL = auto()
        INT = auto()
        STR = auto()


    def __init__(self, type, value):
        self.type = type
        self.setValue(value)

    def __str__(self):
        return "("+self.type.name+", "+str(self.value)+")"

    
    def isValCompatible(self, value) -> bool:
        """Checks whther given value of python type is compatible with 
        data type of object

        Args:
            value (any): value that should be checked
        """

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
    """Instances of this class (and subclasses) represent operands.
    Contains type of operand (given by enum type), order number, and
    string content of operand
    """

    class Type(Enum):
        VAR = auto()
        LITERAL = auto()
        LABEL = auto()
        TYPE = auto()


    def __init__(self, num : int, type : Type, content : str):
        self.number = num
        self.type = type
        self.content = content


    def getType(self):
        return self.type

    
    def getNumber(self):
        return self.number


    def getContent(self):
        return self.content



class Literal(Operand):
    """Subclass of Operand. Represents literal operands - additionally
    it contains data property to store corresponding data with its type
    """

    def __init__(self, num : int, content : str, dataType: Data.Type, value):
        super().__init__(num, super().Type.LITERAL, content)
        self.data = Data(dataType, value)


    def getData(self):
        return self.data



class Type(Operand):
    """Subclass of Operand. Represents type operand."""

    def __init__(self, num : int, content: str, typeVal):
        super().__init__(num, super().Type.TYPE, content)
        self.typeVal = typeVal

    
    def getTypeVal(self):
        return self.typeVal



class Variable(Operand):
    """Subclass of Operand. Represents variable operand. Contains also
    frame property and (variable) name property
    """

    class Frame(Enum):
        GLOBAL = auto()
        LOCAL = auto()
        TEMPORARY = auto()


    def __init__(self, num : int, content : str, frame : Frame, name : str):
        super().__init__(num, super().Type.VAR, content)
        self.frame = frame
        self.name = name


    def getFrameMark(self):
        return self.frame
    

    def getName(self):
        return self.name



class Stack:
    """Instances of this class simulate behaviour of ADT stack by array (list)"""

    def __init__(self):
        self.elements = []

    def __str__(self):
        string = ""

        for e in self.elements:
            string += ' | ' if string != "" else ""
            string += str(e)
        
        if self.elements:
            string += " <- TOP"
        else:
            string = "Empty"

        return string


    def getElements(self):
        """Returns all elements in the stack"""

        return self.elements

    
    def setErr(self, emptyErrMsg = None, errCode = INTERNAL_ERROR):
        """Sets error message and error code for exception, that is raised
        when users want to pop empty stack
        """

        self.errMsg = emptyErrMsg
        self.errCode = errCode


    def pop(self, eTol = False):
        """Typical stack pop method. 'eTol' parameter (bool) says if the
        method is resistent against popping empty stack
        """

        if not self.elements and not eTol:
            raise Error.RuntimeError(self.errCode, self.errMsg)
        elif not self.elements and eTol:
            return
        else:
            return self.elements.pop()


    def push(self, element):
        """Typical push method for ADT stack"""

        self.elements.append(element)


    def getTop(self, eTol = False):
        """Typical stack top method. 'eTol' parameter (bool) says if the
        method is resistent against getting the top of empty stack
        """

        if not self.elements and not eTol:
            raise Error.RuntimeError(self.errCode, self.errMsg)
        elif not self.elements and eTol:
            return None
        else:
            return self.elements[-1]


class Frame:
    """Instaces of this class represent frames with variables. Basicaly
    it is wrapper for dictionary containing also some additional methods...
    """

    def __init__(self, initVars = {}):
        self.vars = initVars

    def __str__(self):
        string = "{"

        for v in self.vars:
            string += "; " if string != "{" else ""
            string += v+"="+str(self.vars[v])

        string += "}"

        return string


    def getVars(self):
        return self.vars


    def getVar(self, varName):
        return self.vars[varName]


    def setVar(self, varName, data = None):
        self.vars[varName] = data


class ProgramContext:
    """Represents 'memory' of virtual computer, that executes the code.
    
    Properties:
        nextInstructionIndex (int): index of the next instruction in array
            with currently executed code (if it's None, exec. is in IDLE state)
        currentInstruction (Instruction): buffer for instruction that holds
            currently executed instruction
        currentFunction (str): label, that was lastly called as function
        input (opened file): file from which is read input
        returnCode (int): ret. code of executed program
        totaICounter (int): counter of executed instructions
        frames (dict): dictionary with frames, that store data of variables
        frameStack (Stack): the stack for storing temporary frames
        callStack (Stack): the stack containing infromation for returns
        dataStack (Stack): stack with data
        labelMap (dict): contains map of labels (association between labels and
            indexed in array with instructions)
    """

    def __init__(self, input = sys.stdin):
        self.nextInstructionIndex = None
        self.currentInstruction = None
        self.currentFunction = None
        self.input = input
        self.returnCode = None
        self.totalICounter = 0

        self.frames = {
            Variable.Frame.GLOBAL : Frame(),
            Variable.Frame.LOCAL : None,
            Variable.Frame.TEMPORARY : None,
        }

        self.frameStack = Stack()
        self.frameStack.setErr("Empty FRAME stack! Unable to access top/pop it!", FRAME_NOT_EXISTS)
        
        self.callStack = Stack()
        self.callStack.setErr("Empty CALL stack! Unable to access top/pop it!", MISSING_VALUE)
        
        self.dataStack = Stack()
        self.dataStack.setErr("Empty DATA stack! Unable to access top/pop it!", MISSING_VALUE)

        self.labelMap = {}


    def getTotalICounter(self) -> int:
        return self.totalICounter

    def incTotalICounter(self):
        self.totalICounter += 1

    def resetTotalICounter(self):
        self.totalICounter = 0


    def getNextInstructionIndex(self) -> int:
        return self.nextInstructionIndex

    def setNextInstructionIndex(self, index : int):
        self.nextInstructionIndex = index

    
    def getInstruction(self):
        return self.currentInstruction

    def setInstruction(self, instruction):
        self.currentInstruction = instruction


    def setCurrentFunction(self, fName : str):
        self.currentFunction = fName

    def getCurrentFunction(self) -> str:
        return self.currentFunction


    def setReturnCode(self, value : int):
        self.returnCode = value

    def getReturnCode(self) -> int:
        return self.returnCode


    def clearLabelMap(self):
        """Deletes all mapped labels from labelMap dictionary"""

        self.labelMap = {}


    def getLabelMap(self) -> dict:
        return self.labelMap


    def addLabel(self, name : str, targetIndex : int):
        """Adds label to labelMap
        
        Args:
            name (str): label
            targetIndex (int): to this index will be performed jumps targeting
                to given label (name)
        """

        if name in self.labelMap:
            raise Error.SemanticError(SEMANTIC_ERROR, "Redefinition of label "+name+"!")
        else:
            self.labelMap[name] = targetIndex


    def getLabelIndex(self, name : str) -> int:
        """Returns target index of given label"""

        if not name in self.labelMap:
            raise Error.SemanticError(SEMANTIC_ERROR, "Undefined label "+name+"!")
        else:
            return self.labelMap[name]


    def getFrame(self, frameMark : Variable.Frame) -> dict:
        """Returns specific frame from dictionary with frames"""

        if not frameMark in self.frames or self.frames[frameMark] == None:
            raise Error.RuntimeError(FRAME_NOT_EXISTS, "Frame '"+frameMark.name+"' does not exists!", self)
        else:
            return self.frames[frameMark]


    def addVar(self, var : Variable):
        """Safely add var to corresponding frame"""

        name = var.getName()
        frameMark = var.getFrameMark()
        frame = self.getFrame(frameMark)

        if var in frame.getVars():
            raise Error.RuntimeError(SEMANTIC_ERROR, "Redefinition of variable "+name+" in frame "+frameMark.name, self)
        else:
            frame.setVar(name)


    def checkVar(self, var : Variable, canBeUninit = False):
        """Checks if given variable exists in program context.
        
        Args:
            var (Variable): variable to be cheked
            canBeUninit (bool): if it is False, it raises error when variable
                is not initialized

        Returns:
            (tuple) frameMark and name of variable
        """

        name = var.getName()
        frameMark = var.getFrameMark()
        frame = self.getFrame(frameMark)

        if not name in frame.getVars():
            raise Error.RuntimeError(VAR_NOT_EXISTS, "Variable '"+name+"' does not exists in frame "+frameMark.name+"!", self)
        elif frame.getVar(name) == None and not canBeUninit:
            raise Error.RuntimeError(MISSING_VALUE, "Missing value of '"+name+"' in frame "+frameMark.name+"!", self)
        else:
            return frame, name


    def getVar(self, var : Variable, canBeUninit = False):
        """Returns data of given variable
        
        Args:
            var (Variable): variable its data will be returned
            canBeUninit (bool): if it is False, it raises error when variable
                is not initialized

        Returns:
            (Data|None)
        """

        frame, name = self.checkVar(var, canBeUninit)

        return frame.getVar(name)


    def setVar(self, varObj : Variable, newData : Data):
        """Assigns data to variable"""

        frame, name = self.checkVar(varObj, canBeUninit=True) # Check if variable exists in frame
    
        frame.setVar(name, newData) # Assigning new data to it

    
    def getData(self, operand : Operand) -> Data:
        """Gets data of operand in current program context. It determines
        where data are stored (if it is literal it queries it from operand,
        if it is variable it get data from corresp. frame)
        """

        if operand.getType() == Operand.Type.LITERAL:
            return operand.getData()
        elif operand.getType() == Operand.Type.VAR:
            return self.getVar(operand)
        else:
            raise Error.InternalError(INTERNAL_ERROR, "Unable to get data of operand of type "+operand.getType().name+"!")

    
    def newTempFrame(self):
        """Creates new temporary frame and throw away the old one"""

        self.frames[Variable.Frame.TEMPORARY] = Frame()


    def clearTempFrame(self):
        """Clears temporary frame to initial state"""

        self.frames[Variable.Frame.TEMPORARY] = None


    def updateLocalFrame(self):
        """Updates local frame - it is recommended to call it everytime
        the frameStack is updated
        """

        self.frames[Variable.Frame.LOCAL] = self.frameStack.getTop(eTol=True)  



class Instruction:
    """Inner representation of instruction. Contains opcode (str), 
    order number (int), operands (array with Operand objects)
    """

    def __init__(self, opcode : str, order : int, operands):
        self.opcode = opcode
        self.order = order
        self.operands = operands

    def __str__(self):
        return self.opcode+", o. "+str(self.order)
    

    def getOpCode(self):
        return self.opcode


    def getOrder(self):
        return self.order


    def getOperands(self):
        return self.operands



class Executable(Instruction):
    """Subclass of instruction class for all instruction, that can be executed
    in the runtime (everything except labels)
    """

    def __init__(self, opcode: str, order: int, operands, action):
        super().__init__(opcode, order, operands)
        self.action = action


    def do(self, ctx : ProgramContext):
        """Executes the implementation of instruction"""

        self.action(ctx, self.operands)



class Label(Instruction):
    """Subclass of instruction class for labels"""

    def do(self, ctx : ProgramContext):
        pass



class Program:
    """Inner representation of input program. Contains sorted list with
    instructions, program context for storing data and methods to
    manipulate with these properties.
    """

    def __init__(self, input = sys.stdin, instructions = []):
        """If the new program is created, the new program context is created"""

        self.ctx = ProgramContext(input)

        self.instructions = []
        for i in instructions:
            self.addInstruction(i)


    def getContext(self):
        return self.ctx


    def mapLabels(self):
        """Performs mapping all labels to its indexes into context label map 
        (used when jump instruction are executed)
        """

        for index, i in enumerate(self.instructions):
            if i.__class__ == Label:
                self.ctx.addLabel(i.operands[0].getContent(), index)


    def addInstruction(self, instruction : Instruction):
        """Adds instruction to list of the instructions and checks if is is
        sorted properly
        """

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
        """Sets position in program to start"""

        self.ctx.setNextInstructionIndex(0)


    def finish(self):
        """Sets program to idle mode"""

        self.ctx.setNextInstructionIndex(None)


    def nextInstruction(self):
        """Incremets next instruction index in program context (if program
        is in Idle state it does nothing)
        """

        nextInstructionIndex = self.ctx.getNextInstructionIndex()
        
        if nextInstructionIndex == None:
            pass
        else:
            self.ctx.setNextInstructionIndex(1 + nextInstructionIndex)


    def reset(self):
        """Resets program to initial state and sets position in instruction 
        list to the start
        """

        self.ctx.setReturnCode(None)
        self.ctx.resetTotalICounter()

        if self.instructions:
            self.start()
        else:
            self.finish()


    def hasEnded(self):
        """Checks if the program is in Idle state (it was not started or it
        was ended)
        """

        return self.ctx.nextInstructionIndex == None


    def run(self):
        """Performs the execution of the program"""

        self.mapLabels()
        self.reset()
        while not self.hasEnded():
            current = self.instructions[self.ctx.getNextInstructionIndex()]

            self.ctx.setInstruction(current)
            current.do(self.ctx)

            self.ctx.incTotalICounter()

            if self.hasEnded(): # Check if instruction terminated program
                break
            
            self.nextInstruction()

            if self.ctx.getNextInstructionIndex() >= len(self.instructions):
                self.finish()
