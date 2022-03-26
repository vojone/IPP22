# IPP project 2. part
# Author: Vojtech Dvorak (xdvora3o)

"""Contains class with implementation of instructions (Op) and class with
description of input lanugaugage (Lang)
"""

import re
from errors import *
from program import Data, Literal, Operand, ProgramContext, Variable


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
        TFToBePushed = ctx.getFrame(Variable.Frame.TEMPORARY)
        ctx.frameStack.push(TFToBePushed)
        ctx.updateLocalFrame() # Stack with frames is changing -> need to update LF
        ctx.clearTempFrame() # Making TF undefined


    def popFrame(ctx : ProgramContext, args : list):
        ctx.frames[Variable.Frame.TEMPORARY] = ctx.frameStack.pop()
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


    def integerArithmetics3(ctx : ProgramContext, args : list, f : str = 'ADD'):
        dst = args[0]
        lOp = ctx.getData(args[1])
        rOp = ctx.getData(args[2])

        if lOp.getType() != Data.Type.INT or rOp.getType() != Data.Type.INT:
            raise Error.RuntimeError(BAD_TYPES, "Integer arithmetic instruction can operate only with INTEGERS!", ctx)

        result = None
        if f == 'SUB':
            result = lOp.getValue() - rOp.getValue()
        elif f == 'MULT':
            result = lOp.getValue() * rOp.getValue()
        elif f == 'IDIV':
            if rOp.getValue() == 0:
                raise Error.RuntimeError(BAD_VALUE, "Division by ZERO!", ctx)
            result = lOp.getValue() // rOp.getValue()
        else:
            result = lOp.getValue() + rOp.getValue()

        ctx.setVar(dst, Data(Data.Type.INT, result))


    def add(ctx : ProgramContext, args : list):
        __class__.integerArithmetics3(ctx, args, 'ADD')


    def sub(ctx : ProgramContext, args : list):
        __class__.integerArithmetics3(ctx, args, 'SUB')


    def mul(ctx : ProgramContext, args : list):
        __class__.integerArithmetics3(ctx, args, 'MULT')


    def idiv(ctx : ProgramContext, args : list):
        __class__.integerArithmetics3(ctx, args, 'IDIV')


    def comparing3(ctx : ProgramContext, args : list, f : str = 'EQ'):
        dst = args[0]
        lOp = ctx.getData(args[1])
        rOp = ctx.getData(args[2])

        if lOp.getType() != rOp.getType():
            raise Error.RuntimeError(BAD_TYPES, "Compared values must have same type (or with equality, there can be NIL type)!", ctx)

        result = None
        if f == 'EQ':
            result = lOp.getValue() == rOp.getValue()
        else:
            if lOp.getType() == Data.Type.NIL or rOp.getType() == Data.Type.NIL:
                raise Error.RuntimeError(BAD_TYPES, "NIL can be compared only with equality!", ctx)
            
            if f == 'LT':
                result = lOp.getValue() < rOp.getValue()
            else:
                result = lOp.getValue() > rOp.getValue()

        ctx.setVar(dst, Data(Data.Type.BOOL, result))

    
    def lt(ctx : ProgramContext, args : list):
        __class__.comparing3(ctx, args, 'LT')


    def gt(ctx : ProgramContext, args : list):
        __class__.comparing3(ctx, args, 'GT')


    def eq(ctx : ProgramContext, args : list):
        __class__.comparing3(ctx, args, 'EQ')


    def logic3(ctx : ProgramContext, args : list, f : str = 'NOT'):
        dst = args[0]
        lOp = ctx.getData(args[1])

        if lOp.getType() != Data.Type.BOOL:
            raise Error.RuntimeError(BAD_TYPES, "Logical functions can operate only with BOOL values!", ctx)

        result = None
        if f in ['AND', 'OR']:
            rOp = ctx.getData(args[2])

            if rOp.getType() != Data.Type.BOOL:
                raise Error.RuntimeError(BAD_TYPES, "Both operands of logical functions must have BOOL values!", ctx)
            
            if f == 'AND':
                result = lOp.getValue() and rOp.getValue()
            else:
                result = lOp.getValue() or rOp.getValue()
        else:
            result = not lOp.getValue()

        ctx.setVar(dst, Data(Data.Type.BOOL, result))


    def andF(ctx : ProgramContext, args : list):
        __class__.logic3(ctx, args, 'AND')


    def orF(ctx : ProgramContext, args : list):
        __class__.logic3(ctx, args, 'OR')


    def notF(ctx : ProgramContext, args : list):
        __class__.logic3(ctx, args, 'NOT')


    def int2char(ctx : ProgramContext, args : list):
        dst = args[0]
        ordinal = ctx.getData(args[1])

        if ordinal.getType() != Data.Type.INT:
            raise Error.RuntimeError(BAD_TYPES, "Bad data types of operands of instruction INT2CHAR (expected INTEGER)!", ctx)

        try:
            char = chr(ordinal)
        except ValueError:
            raise Error.RuntimeError(INVALID_STRING_OP, "Invalid unicode ordinal value! Cannot be converted!", ctx)

        ctx.setVar(dst, Data(Data.Type.STR, char))


    def getCharAtIndex(ctx : ProgramContext, args : list) -> str:
        string = ctx.getData(args[1])
        index = ctx.getData(args[2])

        t = Data.Type
        if string.getType() != t.STR or index.getType() != t.INT:
            raise Error.RuntimeError(BAD_TYPES, "Bad data types of operands of instruction STRI2INIT!", ctx)

        indexInt = index.getValue()
        stringLen = len(string.getValue())

        # It is possible to index string from back (by negative numbers)
        if indexInt >= stringLen or indexInt < -stringLen:
            raise Error.RuntimeError(INVALID_STRING_OP, "Index outside string!", ctx)

        return string.getValue()[indexInt]


    def stri2int(ctx : ProgramContext, args : list):
        dst = args[0]

        # String is in unicode so it should be valid character for ord
        result = ord(__class__.getCharAtIndex(ctx, args))
        ctx.setVar(dst, Data(Data.Type.INT, result))


    def read(ctx : ProgramContext, args : list):
        dst = args[0]
        type = args[1].getTypeVal()

        if type not in [Data.Type.INT, Data.Type.STR, Data.Type.BOOL]:
            raise Error.RuntimeError(BAD_VALUE, "Type argument must be int|str|bool!", ctx)

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
            raise Error.RuntimeError(BAD_TYPES, "Expected STR values as operands of CONCAT!", ctx)

        ctx.setVar(dst, Data(t.STR, lPart.getValue() + rPart.getValue()))


    def strlen(ctx : ProgramContext, args : list):
        dst = args[0]
        string = ctx.getData(args[1])

        if string.getType() != Data.Type.STR:
            raise Error.RuntimeError(BAD_TYPES, "Expected STR value as operand of STRLEN!", ctx)

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
            raise Error.RuntimeError(BAD_TYPES, "Invalid data types of operands of SETCHAR instruction!", ctx)

        indexInt = index.getValue()
        if not src.getValue():
            raise Error.RuntimeError(INVALID_STRING_OP, "Last operand of SETCHAR cannot be empty string!", ctx)
        if indexInt >= len(dst.getValue()) or indexInt < len(dst.getValue()):
            raise Error.RuntimeError(INVALID_STRING_OP, "Index outside string!", ctx)

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
            t.NIL : "nil", t.STR : "string"
        }

        result = None
        if examinigElement.getType() == Operand.Type.VAR:
            result = ctx.getVar(examinigElement, True)
            result = "" if result == None else result
        else:
            exData = ctx.getData(examinigElement)
            result = str2DataType[exData.getType()]

        ctx.setVar(dst, Data(t.STR, result))


    def jump(ctx : ProgramContext, args : list):
        targetLabel = args[0].getContent()
        targetIndex = ctx.getLabelIndex(targetLabel)
        ctx.setNextInstructionIndex(targetIndex)


    def conditionEval(ctx : ProgramContext, args : list, f : str = 'EQ') -> bool:
        lOp = ctx.getData(args[1])
        rOp = ctx.getData(args[2])

        lOpType = lOp.getType()
        rOpType = rOp.getType()
        t = Data.Type
        if lOpType != rOpType or (lOpType == t.NIL and rOpType == t.NIL):
            raise Error.RuntimeError(BAD_TYPES, "Incompatible types of conditional jump (expected same types or one NIL type)", ctx)

        if f == 'NEQ':
            return lOp.getValue() != rOp.getValue()
        else:
            return lOp.getValue() == rOp.getValue()


    def jumpifeq(ctx : ProgramContext, args : list):
        if __class__.conditionEval(ctx, args, 'EQ'):
            __class__.jump(ctx, args)


    def jumpifneq(ctx : ProgramContext, args : list):
        if __class__.conditionEval(ctx, args, 'NEQ'):
            __class__.jump(ctx, args)


    def exitF(ctx : ProgramContext, args : list):
        exitCode = ctx.getData(args[0])

        if exitCode.getType() != Data.Type.INT:
            raise Error.RuntimeError(BAD_TYPES, "Expected INT value as return code!", ctx)
        
        min = 0
        max = 49
        if exitCode.getValue() < min or exitCode.getValue() > max:
            raise Error.RuntimeError(BAD_VALUE, "Exit code must be integer between "+str(min)+" and "+str(max)+" (included)!", ctx)

        ctx.setReturnCode(exitCode.getValue())
        ctx.setNextInstructionIndex(None)


    def dprint(ctx : ProgramContext, args : list):
        toPrint = __class__.getPrintableValue(ctx, args)
        print(toPrint, end='', file=sys.stderr)


    def breakF(ctx : ProgramContext, args : list):
        total = ctx.getTotalICounter()
        order = ctx.getInstruction()

        lf = ctx.frames[Variable.Frame.LOCAL]
        gf = ctx.frames[Variable.Frame.GLOBAL]
        tf = ctx.frames[Variable.Frame.TEMPORARY]

        print("-------------", file=sys.stderr)
        print("Break at: "+str(order)+" (executed i.: "+str(total)+")", end='\n\n', file=sys.stderr)
        print("Variable frames: ", file=sys.stderr)
        print("GF: "+str(gf), file=sys.stderr)
        print("LF: "+str(lf if lf != None else "Undef."), file=sys.stderr)
        print("TF: "+str(tf if tf != None else "Undef."), end='\n\n', file=sys.stderr)
        print("Call st.: "+str(ctx.callStack), end='\n\n', file=sys.stderr)
        print("Data stack: "+str(ctx.dataStack), end='\n\n', file=sys.stderr)
        print("Frame stack: "+str(ctx.frameStack), end='\n\n', file=sys.stderr)
        print("-------------", file=sys.stderr)

    def nop(ctx : ProgramContext, args : list):
        pass


class Lang:
    """Contains specification of source language elements and converter 
    statical methods

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
        # IO
        "READ"              : [Op.read, ["var", "type"]],
        "WRITE"             : [Op.write, ["symb"]],
        # String operations
        "CONCAT"            : [Op.concat, ["var", "symb", "symb"]],
        "STRLEN"            : [Op.strlen, ["var", "symb"]],
        "GETCHAR"           : [Op.getchar, ["var", "symb", "symb"]],
        "SETCHAR"           : [Op.setchar, ["var", "symb", "symb"]],
        # Type operations
        "TYPE"              : [Op.typeF, ["var", "type"]],
        # Branching
        "LABEL"             : [Op.nop, ["label"]],
        "JUMP"              : [Op.jump, ["label"]],
        "JUMPIFEQ"          : [Op.jumpifeq, ["label", "symb", "symb"]],
        "JUMPIFNEQ"         : [Op.jumpifneq, ["label", "symb", "symb"]],
        "EXIT"              : [Op.exitF, ["symb"]],
        # Debugging
        "DPRINT"             : [Op.dprint, ["symb"]],
        "BREAK"              : [Op.breakF, []],
    }

    LABEL_INSTRUCTIONS = ["LABEL"]
    JUMP_INSTRUCTIONS = ["JUMP", "JUMPIFEQ", "JUMPIFNEQ", "CALL"]
    NEW_VAR_INSTRUCTIONS = ["DEFVAR"]

    OPERAND_TYPES = {
        "string" : Data.Type.STR,
        "int" : Data.Type.INT,
        "bool" : Data.Type.BOOL,
        "nil" : Data.Type.NIL,

        "var" : Operand.Type.VAR,
        "label" : Operand.Type.LABEL,
        "type" : Operand.Type.TYPE
    }

    FRAMES = {
        "GF" : Variable.Frame.GLOBAL,
        "LF" : Variable.Frame.LOCAL,
        "TF" : Variable.Frame.TEMPORARY
    }

    OPERAND_FORMAT = {
        Data.Type.NIL : "^nil$",
        Data.Type.STR : "^(([^\u0000-\u0020\s\\\]|(\\\[0-9]{3}))*|nil)$",
        Data.Type.INT : "^[-\+]?(([1-9]((_)?[0-9]+)*)|(0[oO]?[0-7]((_)?[0-7]+)*)|(0[xX][0-9A-Fa-f]((_)?[0-9A-Fa-f]+)*)|(0)|nil)$",
        Data.Type.BOOL: "^(true|false|nil)$",

        Operand.Type.LABEL : "^[a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*$",
        Operand.Type.TYPE  : "^(bool|int|string|nil)$",
        Operand.Type.VAR   : "^(GF|LF|TF)@[a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*$"
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


    @staticmethod
    def isInstruction(str : str) -> bool:
        return str in __class__.INSTRUCTIONS


    @staticmethod
    def isType(str : str) -> bool:
        return str in __class__.OPERAND_TYPES


    @staticmethod
    def str2frame(str : str) -> str:
        return __class__.FRAMES[str]


    @staticmethod
    def isFrame(str : str) -> bool:
        return str in __class__.FRAMES


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
    def isNewVarInstruction(opcode : str) -> bool:
        """Checks whether the instructions declares new variable (for semantic checks)"""

        return opcode in __class__.NEW_VAR_INSTRUCTIONS


    @staticmethod
    def splitVarName(varName : str):
        """Splits whole variable name to frame name and variable name"""

        frame, name = varName.split(__class__.VAR_DELIM_CHAR)
        
        return frame, name


    @staticmethod
    def isLabelInstrucion(opcode : str) -> bool:
        """Checks whether instruction defines new label (for semantic checks)"""

        return opcode in __class__.LABEL_INSTRUCTIONS


    @staticmethod
    def isJumpInstruction(opcode : str) -> bool:
        """Checks whether instruction perform jump (usefull for semantic checks)"""

        return opcode in __class__.JUMP_INSTRUCTIONS


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
            else:
                return False
        else:
            return False
