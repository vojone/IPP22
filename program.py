# IPP project 2. part
# Author: Vojtech Dvorak (xdvora3o)

"""Contains all classes responsible for storing inner representation of analyzed
input code, for storing execution context and finally also for exectuion itself
"""

from enum import Enum, auto
from fileinput import close
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
        FLOAT = auto()


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
        elif self.type == __class__.Type.FLOAT and value.__class__ == float:
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
            raise Error.InternalError(INTERNAL_ERROR, "Nelze přiřadit hodnotu typu "+newValue.__class__.__name__+" datovému objektu s typem "+self.type.name+"!")


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

    class FrameM(Enum):
        GLOBAL = auto()
        LOCAL = auto()
        TEMPORARY = auto()


    def __init__(self, num : int, content : str, frame : FrameM, name : str):
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

    
    def clear(self):
        """Deletes all elements from stack and brings the stack to 
        the initial state
        """

        self.elements = []


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

        self.frames = { # Initial states of frames
            Variable.FrameM.GLOBAL : Frame(),
            Variable.FrameM.LOCAL : None,
            Variable.FrameM.TEMPORARY : None,
        }

        self.frameStack = Stack()
        self.frameStack.setErr("Prázdný zásobník rámců!", FRAME_NOT_EXISTS)
        
        self.callStack = Stack()
        self.callStack.setErr("Prázdný zásobník volání!", MISSING_VALUE)
        
        self.dataStack = Stack()
        self.dataStack.setErr("Prázdný datový zásobník!", MISSING_VALUE)

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
            raise Error.SemanticError(SEMANTIC_ERROR, "Redefinice návěští "+name+"!")
        else:
            self.labelMap[name] = targetIndex


    def getLabelIndex(self, name : str) -> int:
        """Returns target index of given label"""

        if not name in self.labelMap:
            raise Error.SemanticError(SEMANTIC_ERROR, "Nedefinované návěští "+name+"!")
        else:
            return self.labelMap[name]


    def getFrame(self, frameMark : Variable.FrameM) -> dict:
        """Returns specific frame from dictionary with frames"""

        if not frameMark in self.frames or self.frames[frameMark] == None:
            raise Error.RuntimeError(FRAME_NOT_EXISTS, "Rámec '"+frameMark.name+"' neexistuje!", self)
        else:
            return self.frames[frameMark]


    def addVar(self, var : Variable):
        """Safely add var to corresponding frame"""

        name = var.getName()
        frameMark = var.getFrameMark()
        frame = self.getFrame(frameMark)

        if var in frame.getVars():
            raise Error.RuntimeError(SEMANTIC_ERROR, "Redefinice proměnné "+name+" v rámci "+frameMark.name, self)
        else:
            frame.setVar(name, None)


    def checkVar(self, var : Variable, canBeUninit = False):
        """Checks if given variable exists in program context.
        
        Args:
            var (Variable): variable to be checked
            canBeUninit (bool): if it is False, it raises error when variable
                is not initialized

        Returns:
            (tuple) frameMark and name of variable
        """

        name = var.getName()
        frameMark = var.getFrameMark()
        frame = self.getFrame(frameMark)

        if not name in frame.getVars():
            raise Error.RuntimeError(VAR_NOT_EXISTS, "Proměnná '"+name+"' neexistuje v rámci "+frameMark.name+"!", self)
        elif frame.getVar(name) == None and not canBeUninit:
            raise Error.RuntimeError(MISSING_VALUE, "Neinicializovaná proměnná '"+name+"' v rámci "+frameMark.name+"!", self)
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
            return self.getVar(operand, False)
        else:
            raise Error.InternalError(INTERNAL_ERROR, "Nelze získat data z operandu "+operand.getType().name+"!")

    
    def newTempFrame(self):
        """Creates new temporary frame and throw away the old one"""

        self.frames[Variable.FrameM.TEMPORARY] = Frame({})


    def deleteTempFrame(self):
        """Clears temporary frame to initial state"""

        self.frames[Variable.FrameM.TEMPORARY] = None


    def updateLocalFrame(self):
        """Updates local frame - it is recommended to call it everytime
        the frameStack is updated
        """

        self.frames[Variable.FrameM.LOCAL] = self.frameStack.getTop(eTol=True)  



class StatsCollector:
    """Its instances are reponsible for collecting stats about interpretation"""

    SKEY = "stats" # Key that is used in dictionary with configuration


    def __init__(self, config):
        """Initializes statistics information and saves the stats config"""

        self.sconfig = {}

        if __class__.SKEY in config:
            self.sconfig = config[__class__.SKEY]

        self.insts = 0
        self.hotInstruction = None
        self.vars = 0

        self.hotMap = {}


    def updateInsts(self, ctx : ProgramContext):
        """Updates executed instruction counter"""

        self.insts = ctx.getTotalICounter()


    def updateHot(self, ctx : ProgramContext):
        """Updates the hottest instruction"""

        curInstr = ctx.getInstruction()
        if curInstr in self.hotMap:
            self.hotMap[curInstr] += 1
        else: 
            self.hotMap[curInstr] = 1

        if self.hotInstruction == None:
            self.hotInstruction = curInstr
        else:
            hot = self.hotInstruction

            hotN = self.hotMap[hot]
            curN = self.hotMap[curInstr]

            hotO = hot.getOrder()
            curO = curInstr.getOrder()

            if (curN > hotN) or (curN == hotN and curO < hotO):
                self.hotInstruction = curInstr
    

    def updateVars(self, ctx : ProgramContext):
        """Updates maximum declared variables number"""
        
        curVars = 0
        for frame in ctx.frames:
            if ctx.frames[frame]:
                curVars += len(ctx.frames[frame].getVars())
        
        if curVars > self.vars:
            self.vars = curVars
        

    def updateAll(self, ctx : ProgramContext):
        """Updates statistics information (if there was option --stats)"""

        if self.sconfig:
            self.updateHot(ctx)
            self.updateVars(ctx)
            self.updateInsts(ctx)

    
    def report(self):
        """Prints statistics into corresponding files (given in config)"""

        for file in self.sconfig:
            try:
                fStream = open(file, "w")
            except:
                raise Error.FileError(OUPUT_FILE_ERROR, "Nelze vytvořit/zapsat statistiky do souboru '"+file+"'!")

            for stat in self.sconfig[file]:
                data = None

                if stat == "insts":
                    data = self.insts
                elif stat == "hot":
                    data = self.hotInstruction.getOrder()
                elif stat == "vars":
                    data = self.vars
                else:
                    raise Error.InternalError(INTERNAL_ERROR, "Nepodporovaný typ statistiky '"+stat+"'!")
                
                print(data, file=fStream, end="\n")

            fStream.close()



class Instruction:
    """Inner representation of instruction. Used design pattern
    COMMAND to design this class. Instruction objects ar firstly created
    (corresponding method of executor is assigned to it) and then they are
    executed one by one.

    Contains opcode (str), order number (int), operands 
    (array with Operand objects)
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


    def do(self, ctx : ProgramContext, statCol : StatsCollector):
        """Executes the implementation of instruction"""

        self.action(ctx, self.operands)
        ctx.incTotalICounter()

        statCol.updateAll(ctx)


class Debug(Executable):
    """Subclass of executable class for debug instructions (they do not update
    the instruction counter and statistics)
    """

    def do(self, ctx : ProgramContext, statCol : StatsCollector):
        self.action(ctx, self.operands)


class Label(Instruction):
    """Subclass of instruction class for labels"""

    def do(self, ctx : ProgramContext, statCol : StatsCollector):
        pass
    


class Program:
    """Inner representation of input program. Contains sorted list with
    instructions, program context for storing data and methods to
    manipulate with these properties.
    """

    def __init__(self, config : dict, instructions = []):
        """If the new program is created, the new program context is created"""

        self.ctx = ProgramContext(config["inputOpened"])

        self.statCol = StatsCollector(config)

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

    
    def getStatCollector(self):
        return self.statCol


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
            current.do(self.ctx, self.statCol)

            if self.hasEnded(): # Check if instruction terminated program
                break
            
            self.nextInstruction()

            if self.ctx.getNextInstructionIndex() >= len(self.instructions):
                self.finish()
