import codecs
import re
from typing import TYPE_CHECKING, Type
from program import Data, Literal, Operand, ProgramContext, Variable


class Op:
    def defVar(ctx : ProgramContext, args : list):
        pass

    def move(ctx : ProgramContext, args : list):
        pass

    def jump(ctx : ProgramContext, args : list):
        pass

    def nop(ctx : ProgramContext, args : list):
        pass



class Lang:
    FUNC_INDEX = 0
    OP_INDEX = 1

    INSTRUCTIONS = {
        # Frame operations, function calls 
        "MOVE"              : [Op.nop , ["var", "symb"]],
        "CREATEFRAME"       : [Op.nop , []],
        "PUSHFRAME"         : [Op.nop , []],
        "POPFRAME"          : [Op.nop , ["var", "symb"]],
        "DEFVAR"            : [Op.nop , ["var"]],
        "CALL"              : [Op.nop , ["label"]],
        "RETURN"            : [Op.nop , []],
        # Data stack operations
        "PUSHS"             : [Op.nop , ["symb"]],
        "POPS"              : [Op.nop , ["var"]],
        # Arithmetics
        "ADD"               : [Op.nop , ["var", "symb", "symb"]],
        "SUB"               : [Op.nop , ["var", "symb", "symb"]],
        "MUL"               : [Op.nop , ["var", "symb", "symb"]],
        "IDIV"              : [Op.nop , ["var", "symb", "symb"]],
        # Comparing
        "LT"                : [Op.nop , ["var", "symb", "symb"]],
        "GT"                : [Op.nop , ["var", "symb", "symb"]],
        "EQ"                : [Op.nop , ["var", "symb", "symb"]],
        # Boolean operations
        "AND"               : [Op.nop , ["var", "symb", "symb"]],
        "OR"                : [Op.nop , ["var", "symb", "symb"]],
        "NOT"               : [Op.nop , ["var", "symb"]],
        # Conversions
        "INT2CHAR"          : [Op.nop , ["var", "symb"]],
        "STRI2INT"          : [Op.nop , ["var", "symb", "symb"]],
        # IO
        "READ"              : [Op.nop , ["var", "type"]],
        "WRITE"             : [Op.nop , ["symb"]],
        # String operations
        "CONCAT"            : [Op.nop , ["var", "symb", "symb"]],
        "STRLEN"            : [Op.nop , ["var", "symb"]],
        "GETCHAR"           : [Op.nop , ["var", "symb", "symb"]],
        "SETCHAR"           : [Op.nop , ["var", "symb", "symb"]],
        # Type operations
        "TYPE"              : [Op.nop , ["var", "type"]],
        # Branching
        "LABEL"             : [Op.nop , ["label"]],
        "JUMP"              : [Op.nop , ["label"]],
        "JUMPIFEQ"          : [Op.nop , ["label", "symb", "symb"]],
        "JUMPIFNEQ"         : [Op.nop , ["label", "symb", "symb"]],
        "EXIT"              : [Op.nop , ["symb"]],
        # Debugging
        "DPRINT"             : [Op.nop , ["symb"]],
        "BREAK"              : [Op.nop , []],
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
        Data.Type.STR : "^(([^\u0000-\u0020\s\\\]|(\\\[0-9]{3}))*)|nil$",
        Data.Type.INT : "^[-\+]?(([1-9]((_)?[0-9]+)*)|(0[oO]?[0-7]((_)?[0-7]+)*)|(0[xX][0-9A-Fa-f]((_)?[0-9A-Fa-f]+)*)|(0)|(nil))$",
        Data.Type.BOOL: "^(true|false|nil)$",

        Operand.Type.LABEL : "^[a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*$",
        Operand.Type.TYPE  : "^(bool|int|string|nil)$",
        Operand.Type.VAR   : "^(GF|LF|TF)@[a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*$"
    }

    VAR_DELIM_CHAR = '@'


    @staticmethod
    def isValidFormated(type, str : str) -> bool:
        if re.search(__class__.OPERAND_FORMAT[type], str):
            return True
        else:
            return False

    @staticmethod
    def str2value(type : Data.Type, str : str):
        """Converts string to python data type"""

        def replaceEscSequence(match : re.Match):
            """Callback function for replacing escape sequences in strings"""

            matchedStr = match.group()
            convertable = matchedStr[1:].lstrip('0') # Removal of initial backslash and leading zeros
            return chr(int(convertable)) # Returning unicode char corresponding to converted number sequence

        if str == "nil" or type == Data.Type.NIL: # Everything can have nil value (and nil type can have only nil value)
            return None

        elif type == Data.Type.BOOL:
            if str == "true":
                return True
            elif str == "false":
                return False

        elif type == Data.Type.STR:
            result = re.sub(r"\\\d{3}", replaceEscSequence, str)
            return result

        elif type == Data.Type.INT:
            # Replacing leading zero for octal format mark (in the source language leading zero means octal)
            withoutLeadingZeros = re.sub("^0", "0o", str)
            return int(withoutLeadingZeros)


    @staticmethod
    def isInstruction(str : str) -> bool:
        return str in __class__.INSTRUCTIONS


    @staticmethod
    def isType(str : str) -> bool:
        return str in __class__.OPERAND_TYPES


    @staticmethod
    def isFrame(str : str) -> bool:
        return str in __class__.FRAMES


    @staticmethod
    def getFunction(opcode : str):
        return __class__.INSTRUCTIONS[opcode][__class__.FUNC_INDEX]


    @staticmethod
    def getOperandTypes(opcode : str):
        return __class__.INSTRUCTIONS[opcode][__class__.OP_INDEX]


    @staticmethod
    def getType(strType : str):
        return __class__.OPERAND_TYPES[strType]


    @staticmethod
    def op2Str(op : Operand):
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
        return opcode in __class__.NEW_VAR_INSTRUCTIONS


    @staticmethod
    def splitVarName(varName : str):
        frame, name = varName.split(__class__.VAR_DELIM_CHAR)
        
        return frame, name


    @staticmethod
    def isLabelInstrucion(opcode : str) -> bool:
        return opcode in __class__.LABEL_INSTRUCTIONS


    @staticmethod
    def isJumpInstruction(opcode : str) -> bool:
        return opcode in __class__.JUMP_INSTRUCTIONS


    @staticmethod
    def isOperandCompatible(operand : Operand, stringSymbol : str):
        """
        Checks if operand is compatible with descripted argument type in 
        INSTRUCTION dictionary
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
