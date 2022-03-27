# IPP project 2. part
# Author: Vojtech Dvorak (xdvora3o)

"""Contains class with implementation of instructions (Op) and class with
description of input lanuguage (Lang). 

Also contains additional class Utils with static methods, that make original 
implementation of instructions more simple (by making multipurpose functions 
for similar operation with same checks)
"""

import re
from errors import *
from program import Data, Literal, Operand, ProgramContext, Variable


class Utils:
    """Contains static methods for simplfying implementations of instructions"""

    @staticmethod
    def arithmetics(ctx : ProgramContext, lOp : Data, rOp : Data, func : str = 'ADD') -> Data:
        """Performs specified arithmetical operation and resturns Data
        object with result or it raises exception

        Args:
            ctx (ProgramContext): current program context (for printing errors)
            lOp (Data): data of the left operand
            rOp (Data): data of the righ operand
            func (str): function specifier it can be ADD|SUB|MULT|DIV|IDIV
        """

        t = Data.Type
        compTypes = [t.INT, t.FLOAT]
        if lOp.getType() not in compTypes or rOp.getType() not in compTypes:
            raise Error.RuntimeError(BAD_TYPES, "Aritmetické instrukce vyžadují operandy typu FLOAT nebo INT!", ctx)

        if lOp.getType() != rOp.getType():
            raise Error.RuntimeError(BAD_TYPES, "Aritmetické instrukce vyžadují operandy stejného typu!", ctx)

        result = None
        if func == 'SUB':
            result = lOp.getValue() - rOp.getValue()

        elif func == 'MUL':
            result = lOp.getValue() * rOp.getValue()

        elif func == 'DIV':
            if rOp.getValue() == 0.0:
                raise Error.RuntimeError(BAD_VALUE, "Dělení nulou!", ctx)
            elif rOp.getType() != t.FLOAT or lOp.getType() != t.FLOAT:
                raise Error.RuntimeError(BAD_TYPES, "Instrukce DIV vyžaduje oba operandy typu FLOAT!", ctx)
            result = lOp.getValue() / rOp.getValue()

        elif func == 'IDIV':
            if rOp.getValue() == 0:
                raise Error.RuntimeError(BAD_VALUE, "Integer division by ZERO!", ctx)
            elif rOp.getType() != t.INT or lOp.getType() != t.INT:
                raise Error.RuntimeError(BAD_TYPES, "Instrukce DIV vyžaduje oba operandy typu INT!", ctx)

            result = lOp.getValue() // rOp.getValue()

        else:
            result = lOp.getValue() + rOp.getValue()

        resultType = t.INT
        if lOp.getType() == t.FLOAT or rOp.getType() == t.FLOAT:
            resultType = t.FLOAT

        return Data(resultType, result)


    @staticmethod
    def arithmetics3(ctx : ProgramContext, args : list, f : str = 'ADD'):
        """Wrapper of arithmetics for three adress instructions"""

        dst = args[0]
        lOp = ctx.getData(args[1])
        rOp = ctx.getData(args[2])

        result = __class__.arithmetics(ctx, lOp, rOp, f)

        ctx.setVar(dst, result)


    @staticmethod
    def stackArithmetics(ctx : ProgramContext, f : str = 'ADD'):
        """Wrapper of arithmetics for stack instructions"""

        rOp = ctx.dataStack.pop()
        lOp = ctx.dataStack.pop()

        result = __class__.arithmetics(ctx, lOp, rOp, f)

        ctx.dataStack.push(result)


    @staticmethod
    def float2int(ctx : ProgramContext, toBeConverted : Data) -> Data:
        """Converts float data to int and returns data object with the result"""

        if toBeConverted.getType() != Data.Type.FLOAT:
            raise Error.RuntimeError(BAD_TYPES, "Neočekávaný typ parametru! Očekáván FLOAT.", ctx)

        return Data(Data.Type.INT, int(toBeConverted.getValue()))


    @staticmethod
    def int2float(ctx : ProgramContext, toBeConverted : Data) -> Data:
        """Converts int data to float and returns data object with the result"""

        if toBeConverted.getType() != Data.Type.INT:
            raise Error.RuntimeError(BAD_TYPES, "Neočekávaný typ parametru! Očekáván INT.", ctx)

        return Data(Data.Type.FLOAT, float(toBeConverted.getValue()))


    @staticmethod
    def comparing(ctx : ProgramContext, lOp : Data, rOp : Data, func : str = 'EQ') -> Data:
        """Performs specified comparison and resturns Data (of type BOOL)
        object with the result or it raises exception

        Args:
            ctx (ProgramContext): current program context (for printing errors)
            lOp (Data): data of the left operand
            rOp (Data): data of the righ operand
            func (str): function specifier it can be EQ|GT|LT
        """

        lOpType = lOp.getType()
        rOpType = rOp.getType()
        t = Data.Type

        result = None
        if func == 'EQ':
            if lOpType != rOpType and lOpType != t.NIL and rOpType != t.NIL:
                raise Error.RuntimeError(BAD_TYPES, "Porovnávané hodnoty musí být stejného typu (nebo jeden operand může být typu NIL)!", ctx)

            result = lOp.getValue() == rOp.getValue()
        else:
            if lOpType != rOpType:
                raise Error.RuntimeError(BAD_TYPES, "Porovnávané hodnoty musí být stejného typu!", ctx)
            
            if func == 'LT':
                result = lOp.getValue() < rOp.getValue()
            else:
                result = lOp.getValue() > rOp.getValue()

        return Data(t.BOOL, result)


    @staticmethod
    def comparing3(ctx : ProgramContext, args : list, f : str = 'EQ'):
        """Wrapper of comparing for three adress instructions"""

        dst = args[0]
        lOp = ctx.getData(args[1])
        rOp = ctx.getData(args[2])

        result = __class__.comparing(ctx, lOp, rOp, f)

        ctx.setVar(dst, result)


    @staticmethod
    def stackComparing(ctx : ProgramContext, f : str = 'EQ'):
        """Wrapper of comparing for stack instructions"""

        rOp = ctx.dataStack.pop()
        lOp = ctx.dataStack.pop()

        result = __class__.comparing(ctx, lOp, rOp, f)

        ctx.dataStack.push(result)


    @staticmethod
    def logic(ctx : ProgramContext, lOp : Data, rOp : Data = None, func : str = 'NOT'):
        """Performs specified logical and resturns Data (of type BOOL)
        object with the result or it raises exception

        Args:
            ctx (ProgramContext): current program context (for printing errors)
            lOp (Data): data of the left operand
            rOp (Data): data of the righ operand
            func (str): function specifier it can be EQ|GT|LT
        """
        
        if lOp.getType() != Data.Type.BOOL:
            raise Error.RuntimeError(BAD_TYPES, "Logické operace mohout operovat pouze s operandy typu BOOL!", ctx)

        result = None
        if func in ['AND', 'OR']:
            if rOp.getType() != Data.Type.BOOL:
                raise Error.RuntimeError(BAD_TYPES, "Logické operace mohout operovat pouze s operandy typu BOOL!", ctx)
            
            if func == 'AND':
                result = lOp.getValue() and rOp.getValue()
            else:
                result = lOp.getValue() or rOp.getValue()
        else:
            result = not lOp.getValue()

        return Data(Data.Type.BOOL, result)

    
    @staticmethod
    def logic3(ctx : ProgramContext, args : list, f : str = 'NOT'):
        """Wrapper of logic for three adress instructions"""

        dst = args[0]
        lOp = ctx.getData(args[1])
        rOp = None

        if f in ['AND', 'OR']:
            rOp = ctx.getData(args[2])

        result = __class__.logic(ctx, lOp, rOp, f)

        ctx.setVar(dst, result)


    @staticmethod
    def stackLogic(ctx : ProgramContext, f : str = 'NOT'):
        """Wrapper of logic for stack instructions"""

        rOp = None
        if f in ['AND', 'OR']:
            rOp = ctx.dataStack.pop()

        lOp = ctx.dataStack.pop()

        result = __class__.logic(ctx, lOp, rOp, f)

        ctx.dataStack.push(result)


    def int2char(ctx : ProgramContext, ordinal : Data):
        """Converts int to character and returns data object (STR) with 
        the result (or it raises exception)
        """

        if ordinal.getType() != Data.Type.INT:
            raise Error.RuntimeError(BAD_TYPES, "Neočekávaný datový typ operandu! Očekáván INT.", ctx)

        try:
            char = chr(ordinal.getValue())
        except ValueError:
            raise Error.RuntimeError(INVALID_STRING_OP, "Neplatná unicode hodnota! Nelze konvertovat!", ctx)

        return Data(Data.Type.STR, char)


    def getCharAtIndex(ctx : ProgramContext, string : Data, index : Data) -> str:
        """Gets character of string at given index and returns data object 
        with the result (with type STR) (or it raises exception)
        """

        t = Data.Type
        if string.getType() != t.STR or index.getType() != t.INT:
            raise Error.RuntimeError(BAD_TYPES, "Neočekávaný datový typ operandů! Očekáván STR a INT.", ctx)

        indexInt = index.getValue()
        stringLen = len(string.getValue())

        # It is possible to index string from back (by negative numbers)
        if indexInt >= stringLen or indexInt < -stringLen:
            raise Error.RuntimeError(INVALID_STRING_OP, "Index mimo hranice řetězce!", ctx)

        return string.getValue()[indexInt]


    def conditionEval(ctx : ProgramContext, lOp : Data, rOp : Data, f : str = 'EQ') -> bool:
        """Evaluates condition (== or !=) of jump and returns boolean value
        wwith the result

        Args:
            lOp (Data): left operand of comparison
            rOp (Data): right opearnd of comparison
            f (str): specifies comparison mode (EQ|NEQ)

        Returns:
            (bool) True if jump should be performed (condition is satisfied)
        """

        lOpType = lOp.getType()
        rOpType = rOp.getType()
        t = Data.Type
        if lOpType != rOpType and lOpType != t.NIL and rOpType != t.NIL:
            raise Error.RuntimeError(BAD_TYPES, "Nekompatibilní datové typy operandů! (oba typy musí být stejné/jeden může být NIL)", ctx)

        if f == 'NEQ':
            return lOp.getValue() != rOp.getValue()
        else:
            return lOp.getValue() == rOp.getValue()



class Op:
    """
    Contains implementation of instrucions, all methods have 2 parameters:
    programContext, that can be changed by instruction, and args list,
    containing list of instruction operands
    """

    def move(ctx : ProgramContext, args : list):
        dst = args[0]
        src = args[1]
        ctx.setVar(dst, ctx.getData(src))


    def createFrame(ctx : ProgramContext, args : list):
        ctx.newTempFrame()


    def pushFrame(ctx : ProgramContext, args : list):
        TFToBePushed = ctx.getFrame(Variable.FrameM.TEMPORARY)
        ctx.frameStack.push(TFToBePushed)
        ctx.updateLocalFrame() # Stack with frames is changing -> need to update LF
        ctx.deleteTempFrame() # Making TF undefined


    def popFrame(ctx : ProgramContext, args : list):
        ctx.frames[Variable.FrameM.TEMPORARY] = ctx.frameStack.pop()
        ctx.updateLocalFrame() # Stack with frames is changing -> need to update LF


    def defVar(ctx : ProgramContext, args : list):
        newVar = args[0]
        ctx.addVar(newVar)

    
    def call(ctx : ProgramContext, args : list):
        retIndex = ctx.getNextInstructionIndex() # Store function (call) context to stack
        retFunc = ctx.getCurrentFunction()
        ctx.callStack.push((retIndex, retFunc))

        __class__.jump(ctx, args) # Jumping to new function (label)
        targetLabel = args[0].getContent()
        ctx.setCurrentFunction(targetLabel)


    def retFromCall(ctx : ProgramContext, args : list):
        retIndex, retFunc = ctx.callStack.pop() # Pick up old function context from stack
        ctx.setNextInstructionIndex(retIndex) # Jumping back
        ctx.setCurrentFunction(retFunc)

    
    def pushs(ctx : ProgramContext, args : list):
        toStore = args[0]
        dataToStore = ctx.getData(toStore)
        ctx.dataStack.push(dataToStore)


    def pops(ctx : ProgramContext, args : list):
        dstVar = args[0]
        poppedData = ctx.dataStack.pop()
        
        ctx.setVar(dstVar, poppedData)


    def add(ctx : ProgramContext, args : list):
        Utils.arithmetics3(ctx, args, 'ADD')


    def sub(ctx : ProgramContext, args : list):
        Utils.arithmetics3(ctx, args, 'SUB')


    def mul(ctx : ProgramContext, args : list):
        Utils.arithmetics3(ctx, args, 'MUL')


    def div(ctx : ProgramContext, args : list):
        Utils.arithmetics3(ctx, args, 'DIV')


    def idiv(ctx : ProgramContext, args : list):
        Utils.arithmetics3(ctx, args, 'IDIV')

    
    def float2int(ctx : ProgramContext, args : list):
        dst = args[0]
        toBeConverted = ctx.getData(args[1])

        result = Utils.float2int(ctx, toBeConverted)

        ctx.setVar(dst, result)


    def int2float(ctx : ProgramContext, args : list):
        dst = args[0]
        toBeConverted = ctx.getData(args[1])

        result = Utils.int2float(ctx, toBeConverted)

        ctx.setVar(dst, result)

    
    def lt(ctx : ProgramContext, args : list):
        Utils.comparing3(ctx, args, 'LT')


    def gt(ctx : ProgramContext, args : list):
        Utils.comparing3(ctx, args, 'GT')


    def eq(ctx : ProgramContext, args : list):
        Utils.comparing3(ctx, args, 'EQ')


    def andF(ctx : ProgramContext, args : list):
        Utils.logic3(ctx, args, 'AND')


    def orF(ctx : ProgramContext, args : list):
        Utils.logic3(ctx, args, 'OR')


    def notF(ctx : ProgramContext, args : list):
        Utils.logic3(ctx, args, 'NOT')


    def int2char(ctx : ProgramContext, args : list):
        dst = args[0]
        ordinalValue = ctx.getData(args[1])

        result = Utils.int2char(ctx, ordinalValue)

        ctx.setVar(dst, result)


    def stri2int(ctx : ProgramContext, args : list):
        dst = args[0]
        string = ctx.getData(args[1])
        index = ctx.getData(args[2])

        # String is in unicode so it should be valid character for ord
        result = ord(Utils.getCharAtIndex(ctx, string, index))

        ctx.setVar(dst, Data(Data.Type.INT, result))


    def read(ctx : ProgramContext, args : list):
        dst = args[0]
        type = args[1].getTypeVal()

        t = Data.Type
        if type not in [t.INT, t.STR, t.BOOL, t.FLOAT]:
            raise Error.RuntimeError(BAD_VALUE, "Argument specifikující typ musí být int|str|bool|float!", ctx)

        strInput = ctx.input.readline().strip()
        strInput = strInput.lower() if type == Data.Type.BOOL else strInput # If it is bool type it does not matter letter case
        inputValue = None 
        isNotNil = strInput.lower() != "nil" or type == Data.Type.STR

        if Lang.isValidFormated(type, strInput) and isNotNil:
            inputValue = Lang.str2value(type, strInput)
        else:
            type = Data.Type.NIL

        ctx.setVar(dst, Data(type, inputValue))


    def getPrintableValue(ctx : ProgramContext, args : list):
        data = ctx.getData(args[0])
        toPrint = data.getValue()

        if data.getType() == Data.Type.BOOL: 
            if toPrint == True:
                toPrint = "true"
            else:
                toPrint = "false"
        elif data.getType() == Data.Type.NIL:
            toPrint = ""
        elif data.getType() == Data.Type.FLOAT:
            toPrint = float.hex(toPrint)

        return toPrint


    def write(ctx : ProgramContext, args : list):
        toPrint = __class__.getPrintableValue(ctx, args)
        print(toPrint, end='')

    
    def concat(ctx : ProgramContext, args : list):
        dst = args[0]
        lPart = ctx.getData(args[1])
        rPart = ctx.getData(args[2])

        t = Data.Type
        if lPart.getType() != t.STR or rPart.getType() != t.STR:
            raise Error.RuntimeError(BAD_TYPES, "Neočekávaný datový typ operandů! Očekáván STR u ubou operandů.", ctx)

        ctx.setVar(dst, Data(t.STR, lPart.getValue() + rPart.getValue()))


    def strlen(ctx : ProgramContext, args : list):
        dst = args[0]
        string = ctx.getData(args[1])

        if string.getType() != Data.Type.STR:
            raise Error.RuntimeError(BAD_TYPES, "Neočekávaný datový typ operandu! Očekáván STR.", ctx)

        ctx.setVar(dst, Data(Data.Type.STR, len(string.getValue())))


    def getchar(ctx : ProgramContext, args : list):
        dst = args[0]

        char = __class__.getCharAtIndex(ctx, args)
        ctx.setVar(dst, Data(Data.Type.STR, char))


    def setchar(ctx : ProgramContext, args : list):
        dstVar = args[0]

        dst = ctx.getData(dstVar)
        index = ctx.getData(args[1])
        src = ctx.getData(args[2])

        t = Data.Type
        dstType = dst.getType()
        srcType = src.getType()
        indexType = index.getType()
        if dstType != t.STR or srcType != t.STR or indexType != t.INT:
            raise Error.RuntimeError(BAD_TYPES, "Neplatné typy operandů!", ctx)

        indexInt = index.getValue()
        if not src.getValue():
            raise Error.RuntimeError(INVALID_STRING_OP, "Operandem nemůže být prázdný řetězec!", ctx)
        if indexInt >= len(dst.getValue()) or indexInt < len(dst.getValue()):
            raise Error.RuntimeError(INVALID_STRING_OP, "Index mimo hranice pole!", ctx)

        result = dst.getValue()
        result[indexInt] = src.getValue()[0]

        ctx.setVar(dst, Data(Data.Type.STR, result))


    def typeF(ctx : ProgramContext, args : list):
        dst = args[0]
        examinigElement = args[1]

        # Conversion table for this function
        t = Data.Type 
        str2DataType = {
            t.BOOL : "bool", t.INT : "int", 
            t.NIL : "nil", t.STR : "string", t.FLOAT : "float"
        }

        typeStr = None
        if examinigElement.getType() == Operand.Type.VAR:
            exData = ctx.getVar(examinigElement, True)
            if exData == None:
                typeStr = ""
            else:
                typeStr = str2DataType[exData.getType()]
        else:
            exData = ctx.getData(examinigElement)
            typeStr = str2DataType[exData.getType()]

        ctx.setVar(dst, Data(t.STR, typeStr))


    def jump(ctx : ProgramContext, args : list):
        targetLabel = args[0].getContent()
        targetIndex = ctx.getLabelIndex(targetLabel)
        ctx.setNextInstructionIndex(targetIndex)


    def jumpifeq(ctx : ProgramContext, args : list):
        lOp = ctx.getData(args[1])
        rOp = ctx.getData(args[2])

        if Utils.conditionEval(ctx, lOp, rOp, 'EQ'):
            __class__.jump(ctx, args)


    def jumpifneq(ctx : ProgramContext, args : list):
        lOp = ctx.getData(args[1])
        rOp = ctx.getData(args[2])

        if Utils.conditionEval(ctx, lOp, rOp, 'NEQ'):
            __class__.jump(ctx, args)


    def exitF(ctx : ProgramContext, args : list):
        exitCode = ctx.getData(args[0])

        if exitCode.getType() != Data.Type.INT:
            raise Error.RuntimeError(BAD_TYPES, "Očekávána hodnota typu INT!", ctx)
        
        min = 0
        max = 49
        if exitCode.getValue() < min or exitCode.getValue() > max:
            raise Error.RuntimeError(BAD_VALUE, "Návratový kód musí být celé číslo mezi "+str(min)+" a "+str(max)+" (včetně)!", ctx)

        ctx.setReturnCode(exitCode.getValue())
        ctx.setNextInstructionIndex(None)


    def dprint(ctx : ProgramContext, args : list):
        toPrint = __class__.getPrintableValue(ctx, args)
        print(toPrint, end='', file=sys.stderr)


    def breakF(ctx : ProgramContext, args : list):
        total = ctx.getTotalICounter()
        order = ctx.getInstruction()
        function = ctx.getCurrentFunction()

        lf = ctx.frames[Variable.FrameM.LOCAL]
        gf = ctx.frames[Variable.FrameM.GLOBAL]
        tf = ctx.frames[Variable.FrameM.TEMPORARY]

        print("________________________", file=sys.stderr)
        print("BREAK at: "+str(order)+" (executed i.: "+str(total)+")", file=sys.stderr)
        print("Function: "+(function if function != None else "-"), end='\n\n', file=sys.stderr)
        print("Variable frames: ", file=sys.stderr)
        print("GF: "+str(gf), file=sys.stderr)
        print("LF: "+str(lf if lf != None else "Undef."), file=sys.stderr)
        print("TF: "+str(tf if tf != None else "Undef."), end='\n\n', file=sys.stderr)
        print("Call st.: "+str(ctx.callStack), end='\n\n', file=sys.stderr)
        print("Data stack: "+str(ctx.dataStack), end='\n\n', file=sys.stderr)
        print("Frame stack: "+str(ctx.frameStack), end='\n\n', file=sys.stderr)
        print("________________________", file=sys.stderr)

    #------------------------------ STACK INSTRUCTIONS ------------------------
    
    def clears(ctx : ProgramContext, args : list):
        ctx.dataStack.clear()

    
    def adds(ctx : ProgramContext, args : list):
        Utils.stackArithmetics(ctx, 'ADD')


    def muls(ctx : ProgramContext, args : list):
        Utils.stackArithmetics(ctx, 'MUL')


    def subs(ctx : ProgramContext, args : list):
        Utils.stackArithmetics(ctx, 'SUB')


    def idivs(ctx : ProgramContext, args : list):
        Utils.stackArithmetics(ctx, 'IDIV')


    def divs(ctx : ProgramContext, args : list):
        Utils.stackArithmetics(ctx, 'DIV')


    def int2floats(ctx : ProgramContext, args : list):
        toBeConverted = ctx.dataStack.pop()

        result = Utils.int2float(ctx, toBeConverted)

        ctx.dataStack.push(result)


    def float2ints(ctx : ProgramContext, args : list):
        toBeConverted = ctx.dataStack.pop()

        result = Utils.float2int(ctx, toBeConverted)

        ctx.dataStack.push(result)


    def lts(ctx : ProgramContext, args : list):
        Utils.stackComparing(ctx, 'LT')


    def gts(ctx : ProgramContext, args : list):
        Utils.stackComparing(ctx, 'GT')


    def eqs(ctx : ProgramContext, args : list):
        Utils.stackComparing(ctx, 'EQ')


    def ands(ctx : ProgramContext, args : list):
        Utils.stackLogic(ctx, 'AND')


    def ors(ctx : ProgramContext, args : list):
        Utils.stackLogic(ctx, 'OR')


    def nots(ctx : ProgramContext, args : list):
        Utils.stackLogic(ctx, 'NOT')


    def int2chars(ctx : ProgramContext, args : list):
        ordinalValue = ctx.dataStack.pop()

        result = Utils.int2char(ctx, ordinalValue)

        ctx.dataStack.push(result)


    def stri2ints(ctx : ProgramContext, args : list):
        index = ctx.dataStack.pop()
        string = ctx.dataStack.pop()

        # String is in unicode so it should be valid character for ord
        result = ord(Utils.getCharAtIndex(ctx, string, index))
        
        ctx.dataStack.push(Data(Data.Type.INT, result))


    def jumpifeqs(ctx : ProgramContext, args : list):
        rOp = ctx.dataStack.pop()
        lOp = ctx.dataStack.pop()

        if Utils.conditionEval(ctx, lOp, rOp, 'EQ'):
            __class__.jump(ctx, args)


    def jumpifneqs(ctx : ProgramContext, args : list):
        rOp = ctx.dataStack.pop()
        lOp = ctx.dataStack.pop()

        if Utils.conditionEval(ctx, lOp, rOp, 'NEQ'):
            __class__.jump(ctx, args)



    def nop(ctx : ProgramContext, args : list):
        pass


class Lang:
    """Contains specification of source language elements and converter 
    static methods

    The most important attributes:
        INSTRUCTIONS (dict): contains table with all supported instructions,
            expected type of their operands and with corresponding function
        OPERAND_TYPES (dict): associates input type of operands with inner
            representation of datatypes (by Data.Type enum)
        FRAMES (dict): associates strings, that specify frames, with values
            of enum Variable.Frame (that is used in script)
        OPERAND_FORMAT (dict): contains regular expression strings for all
            type of operands (to make additiional checks in internal parser)
    """

    FUNC_INDEX = 0
    OP_INDEX = 1

    INSTRUCTIONS = {
        # Frame operations, function calls 
        "MOVE"              : [Op.move, ["var", "symb"]],
        "CREATEFRAME"       : [Op.createFrame, []],
        "PUSHFRAME"         : [Op.pushFrame, []],
        "POPFRAME"          : [Op.popFrame, []],
        "DEFVAR"            : [Op.defVar, ["var"]],
        "CALL"              : [Op.call, ["label"]],
        "RETURN"            : [Op.retFromCall, []],
        # Data stack operations
        "PUSHS"             : [Op.pushs, ["symb"]],
        "POPS"              : [Op.pops, ["var"]],
        # Arithmetics
        "ADD"               : [Op.add, ["var", "symb", "symb"]],
        "SUB"               : [Op.sub, ["var", "symb", "symb"]],
        "MUL"               : [Op.mul, ["var", "symb", "symb"]],
        "DIV"               : [Op.div, ["var", "symb", "symb"]],
        "IDIV"              : [Op.idiv, ["var", "symb", "symb"]],
        # Comparing
        "LT"                : [Op.lt, ["var", "symb", "symb"]],
        "GT"                : [Op.gt, ["var", "symb", "symb"]],
        "EQ"                : [Op.eq, ["var", "symb", "symb"]],
        # Boolean operations
        "AND"               : [Op.andF, ["var", "symb", "symb"]],
        "OR"                : [Op.orF, ["var", "symb", "symb"]],
        "NOT"               : [Op.notF, ["var", "symb"]],
        # Conversions
        "INT2CHAR"          : [Op.int2char, ["var", "symb"]],
        "STRI2INT"          : [Op.stri2int, ["var", "symb", "symb"]],
        "INT2FLOAT"         : [Op.int2float, ["var", "symb"]],
        "FLOAT2INT"         : [Op.float2int, ["var", "symb"]],
        # IO        
        "READ"              : [Op.read, ["var", "type"]],
        "WRITE"             : [Op.write, ["symb"]],
        # String operations
        "CONCAT"            : [Op.concat, ["var", "symb", "symb"]],
        "STRLEN"            : [Op.strlen, ["var", "symb"]],
        "GETCHAR"           : [Op.getchar, ["var", "symb", "symb"]],
        "SETCHAR"           : [Op.setchar, ["var", "symb", "symb"]],
        # Type operations
        "TYPE"              : [Op.typeF, ["var", "symb"]],
        # Branching
        "LABEL"             : [Op.nop, ["label"]],
        "JUMP"              : [Op.jump, ["label"]],
        "JUMPIFEQ"          : [Op.jumpifeq, ["label", "symb", "symb"]],
        "JUMPIFNEQ"         : [Op.jumpifneq, ["label", "symb", "symb"]],
        "EXIT"              : [Op.exitF, ["symb"]],
        # Debugging
        "DPRINT"            : [Op.dprint, ["symb"]],
        "BREAK"             : [Op.breakF, []],

        # Stack variants
        "CLEARS"            : [Op.clears, []],
        "ADDS"              : [Op.adds, []],
        "SUBS"              : [Op.subs, []],
        "MULS"              : [Op.muls, []],
        "IDIVS"             : [Op.idivs, []],
        "DIVS"              : [Op.divs, []],
        "FLOAT2INTS"        : [Op.float2ints, []],
        "INT2FLOATS"        : [Op.int2floats, []],
        "LTS"               : [Op.lts, []],
        "GTS"               : [Op.gts, []],
        "EQS"               : [Op.eqs, []],
        "ANDS"              : [Op.ands, []],
        "ORS"               : [Op.ors, []],
        "NOTS"              : [Op.nots, []],
        "INT2CHARS"         : [Op.int2chars, []],
        "STRI2INTS"        : [Op.stri2ints, []],
        "JUMPIFEQS"         : [Op.jumpifeqs, ["label"]],
        "JUMPIFNEQS"        : [Op.jumpifneqs, ["label"]],
    }

    LABEL_INSTRUCTIONS = ["LABEL"]
    DEBUG_INSTRUCTIONS = ["DPRINT", "BREAK"]
    JUMP_INSTRUCTIONS = ["JUMP", "JUMPIFEQ", "JUMPIFNEQ", "CALL"]
    NEW_VAR_INSTRUCTIONS = ["DEFVAR"]

    OPERAND_TYPES = {
        "string" : Data.Type.STR,
        "int" : Data.Type.INT,
        "bool" : Data.Type.BOOL,
        "nil" : Data.Type.NIL,
        "float" : Data.Type.FLOAT,

        "var" : Operand.Type.VAR,
        "label" : Operand.Type.LABEL,
        "type" : Operand.Type.TYPE
    }

    FRAMES = {
        "GF" : Variable.FrameM.GLOBAL,
        "LF" : Variable.FrameM.LOCAL,
        "TF" : Variable.FrameM.TEMPORARY
    }

    OPERAND_FORMAT = {
        Data.Type.NIL : "^(nil)$",
        Data.Type.STR : "^((.*)|(nil))$",
        Data.Type.INT : "^(([-\+]?(([1-9]((_)?\d+)*)|(0[oO]?[0-7]((_)?[0-7]+)*)|(0[xX][\dA-Fa-f]((_)?[\dA-Fa-f]+)*)|(0)))|(nil))$",
        Data.Type.BOOL: "^(true|false|nil)$",
        Data.Type.FLOAT: "^(([-\+]?(((\d)*[\d\.](\d)*(e[-\+]?\d+)?)|((0[xX][\da-fA-F]*[\da-fA-F\.][\da-fA-F]*)(p[-\+]?[\da-fA-F]+)?)))|(nil))$",

        Operand.Type.LABEL : "^([a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*)$",
        Operand.Type.TYPE  : "^(bool|int|string|float|nil)$",
        Operand.Type.VAR   : "^((GF|LF|TF)@[a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*)$"
    }

    VAR_DELIM_CHAR = '@'


    @staticmethod
    def isValidFormated(type, str : str) -> bool:
        """Checks format of given string that represents operant of 
        given type (in most cases, this method will be unnecessary, because
        there is parse.php, that checks the same thing)

        Args:
            type (Operand.Type): operand type, due to will be check done 
            str (str): input string to be checked
        """

        if re.search(__class__.OPERAND_FORMAT[type], str):
            return True
        else:
            if type == Data.Type.FLOAT:
                # It is little bit tricky to catch all convertable values to float by regex
                # So this is simple workaround

                try:
                    float.fromhex(str)
                except:
                    return False
                else:
                    return True

            else:    
                return False


    @staticmethod
    def str2value(type : Data.Type, string : str):
        """Converts string to python data type
        
        Args:
            type (Data.Type): Type of data that should the result value have
            string (str): input string, that will be converted to data
        """

        def replaceEscSequence(match : re.Match):
            """Callback function for replacing escape sequences in strings"""

            matchedStr = match.group()
            convertable = matchedStr[1:].lstrip('0') # Removal of initial backslash and leading zeros
            return chr(int(convertable)) # Returning unicode char corresponding to converted number sequence

        t = Data.Type
        if (string == "nil" and type != t.STR) or type == t.NIL: # Everything can have nil value (and nil type can have only nil value)
            return None

        elif type == Data.Type.BOOL:
            if string == "true":
                return True
            elif string == "false":
                return False
            else:
                return None

        elif type == Data.Type.STR:
            result = re.sub(r"\\\d{3}", replaceEscSequence, string)
            return result

        elif type == Data.Type.INT:
            # Replacing leading zero for octal format mark (in the source language spec. leading zero means octal format)
            withoutLeadingZeros = re.sub("^(0+)([1-9])", r"0o\2", string)
            return int(withoutLeadingZeros, 0) 
        
        elif type == Data.Type.FLOAT:
            if re.search('^[-\+]?0[xX]', string):
                return float.fromhex(string)
            else:
                # Sometimes it can be difficult to distiguish hexa representation of float and decimal representation
                # That is the reason why this workaround was made (e. g. 1p0 not starts with 0x but is convertable)

                result = None
                try:
                    result = float(string)
                except:
                    result = float.fromhex(string)

                return result


    @staticmethod
    def isInstruction(str : str, caseSensitive = False) -> bool:
        if caseSensitive:
            return str in __class__.INSTRUCTIONS
        else:
            return str.upper() in __class__.INSTRUCTIONS


    @staticmethod
    def isType(str : str, caseSensitive = True) -> bool:
        if caseSensitive:
            return str in __class__.OPERAND_TYPES
        else:
            return str.upper() in __class__.OPERAND_TYPES


    @staticmethod
    def str2frame(str : str) -> str:
        return __class__.FRAMES[str]


    @staticmethod
    def isFrame(str : str, caseSensitive = True) -> bool:
        if caseSensitive:
            return str in __class__.FRAMES
        else:
            return str.upper() in __class__.FRAMES


    @staticmethod
    def getFunction(opcode : str):
        return __class__.INSTRUCTIONS[opcode][__class__.FUNC_INDEX]


    @staticmethod
    def getOperandTypes(opcode : str) -> str:
        return __class__.INSTRUCTIONS[opcode][__class__.OP_INDEX]


    @staticmethod
    def getType(strType : str) -> Operand.Type:
        return __class__.OPERAND_TYPES[strType]


    @staticmethod
    def op2Str(op : Operand) -> str:
        """Converts operand to readable representation
        (e.g. for err. msgs) containing its type
        """

        type = op.getType()
        if type == Operand.Type.VAR:
            return "var"
        elif type == Operand.Type.LITERAL:
            dtype = op.getData().getType()
            if dtype == Data.Type.BOOL:
                return "bool"
            elif dtype == Data.Type.NIL:
                return "nil"
            elif dtype == Data.Type.INT:
                return "int"
            elif dtype == Data.Type.FLOAT:
                return "float"
            elif dtype == Data.Type.STR:
                return "string"
            else:
                return "unknown"
        elif type == Operand.Type.TYPE:
            return "type"
        elif type == Operand.Type.LABEL:
            return "label"
        else:
            return "unknown"


    @staticmethod
    def isNewVarInstruction(opcode : str, caseSensitive = False) -> bool:
        """Checks whether the instructions declares new variable (for semantic checks)"""

        if caseSensitive:
            return opcode in __class__.NEW_VAR_INSTRUCTIONS
        else:
            return opcode.upper() in __class__.NEW_VAR_INSTRUCTIONS


    @staticmethod
    def splitVarName(varName : str):
        """Splits whole variable name to frame name and variable name"""

        frame, name = varName.split(__class__.VAR_DELIM_CHAR)
        
        return frame, name


    @staticmethod
    def isLabelInstrucion(opcode : str, caseSensitive = False) -> bool:
        """Checks whether instruction defines new label (for semantic checks)"""

        if caseSensitive:
            return opcode in __class__.LABEL_INSTRUCTIONS
        else:
            return opcode.upper() in __class__.LABEL_INSTRUCTIONS


    @staticmethod
    def isJumpInstruction(opcode : str, caseSensitive = False) -> bool:
        """Checks whether instruction perform jump (usefull for semantic checks
        and factory methods)
        """

        if caseSensitive:
            return opcode in __class__.JUMP_INSTRUCTIONS
        else:
            return opcode.upper() in __class__.JUMP_INSTRUCTIONS

    
    @staticmethod
    def isDebugInstruction(opcode : str, caseSensitive = False) -> bool:
        """Checks whether instruction is debug instruction"""

        if caseSensitive:
            return opcode in __class__.DEBUG_INSTRUCTIONS
        else:
            return opcode.upper() in __class__.DEBUG_INSTRUCTIONS


    @staticmethod
    def isOperandCompatible(operand : Operand, stringSymbol : str):
        """
        Checks if operand is compatible with descripted argument type in 
        INSTRUCTION dictionary

        Args:
            operand (Operand): input operand converted to operand object
            stringSymbol (str): string symbol from table with instructions
        """

        symbolCompatible = [Operand.Type.VAR, Operand.Type.LITERAL]

        type = operand.getType()
        if stringSymbol == "symb" and type in symbolCompatible:
            return True
        elif stringSymbol == "var" and type == Operand.Type.VAR:
            return True
        elif stringSymbol == "label" and type == Operand.Type.LABEL:
            return True
        elif stringSymbol == "type" and type == Operand.Type.TYPE:
            return True
        elif type == Operand.Type.LITERAL and operand.__class__ == Literal:
            dtype = operand.getData().getType()
            if stringSymbol == "str" and dtype == Data.Type.STR:
                return True
            elif stringSymbol == "int" and dtype == Data.Type.INT:
                return True
            elif stringSymbol == "nil" and dtype == Data.Type.NIL:
                return True
            elif stringSymbol == "bool" and dtype == Data.Type.BOOL:
                return True
            elif stringSymbol == "float" and dtype == Data.Type.FLOAT:
                return True
            else:
                return False
        else:
            return False
