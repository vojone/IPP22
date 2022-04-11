# IPP project 2. part
# Author: Vojtech Dvorak (xdvora3o)

"""Contains classes reponsible for parsing of input XML and for static
semantic analysis
"""

import xml.dom.minidom as xml
import xml.parsers.expat as expat
import re

from errors import *
from program import Data, Debug, Executable, Instruction, Label, Literal, Operand, Type
from program import Program, ProgramContext, Variable
from lang import Lang


class SAnalayzer:
    """Performs static semantic analysis of correctly parsed XML input"""

    def __init__(self):
        self.reset()

    def reset(self):
        """Resets semantic analyzer"""

        self.jumpTargets = {}
        self.fakeCtx = ProgramContext()


    def resolveSAction(self, inst : Instruction, index : int, operands : list):
        """Chooses semantic action due to lanugage specification and modifies
        fake program context due to chosen action

        Args:
            inst (Instruction): currently analyzed instruction
            index (int): index of currently analyzed instruction in instr. list
            operands (list): list of operands of instruction
        """

        opcode = inst.getOpCode()
        order = inst.getOrder()
        
        leadingOp = None
        if operands:
            leadingOp = operands[0]

        if Lang.isLabelInstrucion(opcode):
            self.fakeCtx.addLabel(leadingOp.getContent(), index)
        elif Lang.isJumpInstruction(opcode):
            self.jumpTargets[leadingOp.getContent()] = order
        elif Lang.isNewVarInstruction(opcode):
            if leadingOp.getFrameMark() == Variable.FrameM.GLOBAL:
                self.fakeCtx.addVar(leadingOp)


    def checkSemantics(self, program : Program):
        """Performas static semantic analysis. It can found basic semantic
        errors without executing program such as redefinition of variable
        in global frame, jump to non-existing label...
        
        Aargs:
            program (Program): Program object to be analyzed
        """

        self.reset()
        for index, i in enumerate(program.getInstructions()):
            opcode = i.getOpCode()
            order = i.getOrder()
            operands = i.getOperands()
            expOperandSymbols = Lang.getOperandTypes(opcode)

            nOperands = len(operands)
            nExpOp = len(expOperandSymbols)
            if nOperands != nExpOp: # Check amount of arguments of instruction
                raise Error.SemanticError(BAD_XML, f"Instrukce {opcode} (o. {order}) očekává {nExpOp} operandů, nalezeno {nOperands}!")

            for index, op in enumerate(operands): # Check compatibility of operands
                if not Lang.isOperandCompatible(op, expOperandSymbols[index]):
                    raise Error.SemanticError(SEMANTIC_ERROR, f"Nekompatibilní typ operandu instrukce {opcode}(o. {order})! očekáván {expOperandSymbols[index]}, nalezen {Lang.op2Str(op)}")
        
            self.resolveSAction(i, index, operands)

        for jt in self.jumpTargets: 
            if jt not in self.fakeCtx.getLabelMap():
                raise Error.SemanticError(SEMANTIC_ERROR, "Skok na nedefinované návěští '{jt}'! (o. {self.jumpTargets[jt]})")
            


class IParser:
    """Parses input XML representation. Dependent on xml.dom.minidom and
    xml.parsers.expat modules.
    """

    ROOT_TAG = "program"
    INSTR_TAG = "instruction"
    ARG_TAG_RE = "arg(\d+)"

    ORDER_ATTR = "order"
    OPCODE_ATTR = "opcode"
    TYPE_ATTR = "type"
    LANG_ATTR = "language"

    LANGUAGE = ".IPPcode22"

    def __init__(self, config : dict):
        self.config = config


    @staticmethod
    def safeGetAttribute(attrName : str, node : xml.Node, canBeEmpty = False) -> str:
        """Gets attribute of XML element or it raises exception
        
        Args:
            attrName (str): specifies tha nem of attribute to be get
            node (xml.Node): node with attribute
            canBeEmpty (nool): if it is true it does not raise exception when
                empty string as attribute is found

        Returns:
            (str) attribute content
        """

        if not node.hasAttribute(attrName):
            raise Error.XMLError(BAD_XML, f"Očekávaný atribut: {attrName}, nebyl nalezen!") 

        attr = node.getAttribute(attrName)

        if attr or canBeEmpty:
            return attr
        else:
            raise Error.XMLError(BAD_XML, f"Neočekáváný prázdný atribut {attrName}!") 


    @staticmethod
    def safeGetChildren(childTagNameRE : str, parentNode : xml.Node) -> list:
        """Gets children of given XML element or raises exception
        
        Args:
            childTagNameRE (str): Regular expression specifies, what child 
                elements should be returned (their tag name)
            parentNode (xml.Node): parent node of returned children

        Returns:
            (list): List containing specified XML elements
        """

        children = []
        xmlNodes = parentNode.childNodes

        for n in xmlNodes:
            if n.nodeType == xml.Node.TEXT_NODE and n.nodeValue.strip() == "":
                continue
            elif n.nodeType == xml.Node.COMMENT_NODE:
                continue
            elif n.nodeType == xml.Node.ELEMENT_NODE:
                if re.search("^" + childTagNameRE + "$", n.tagName):
                    children.append(n)
                else:
                    raise Error.XMLError(BAD_XML, f"Neočekávaný XML tag {n.tagName}!")
            else:
                raise Error.XMLError(BAD_XML, f"Neočekávaný XML prvek typu {n.nodeName}!")
        
        return children


    @staticmethod
    def safeGetData(node : xml.Node, canBeEmpty = False) -> str:
        """Returns data of XML node (text node in given node)
        
        Args:
            node (xml.Node): XML node containing data
            canBeEmpty (bool): if it is true it tolerates empty string as data

        Returns:
            (str): data from XML node
        """

        if not node.firstChild and not canBeEmpty:
            raise Error.XMLError(BAD_XML, f"Očekáván text uvnitř tagu {node.tagName}!")
        elif not node.firstChild and canBeEmpty:
            return ""

        if node.firstChild.nodeType != xml.Node.TEXT_NODE:
            raise Error.XMLError(BAD_XML, f"Očekáván text uvnitř tagu {node.tagName}, nalezen jiný obsah!")
        elif len(node.childNodes) > 1:
            for index, ch in enumerate(node.childNodes):
                if index == 0:
                    continue
                if ch.nodeType == xml.Node.COMMENT_NODE:
                    raise Error.XMLError(BAD_XML, f"Očekáván POUZE text uvnitř tagu {node.tagName}, nalezen jiný obsah!")
        
        data = node.firstChild.data.strip()
        if not data and not canBeEmpty:
            raise Error.XMLError(BAD_XML, f"Neočekávaný prázdný řetězec uvnitř tagu {node.tagName}!")

        return data


    @staticmethod
    def safeGetOrder(instr : xml.Node) -> int:
        """Gets attribute number of given XML element with instruction
        or it raises exception
        """

        order = __class__.safeGetAttribute(__class__.ORDER_ATTR, instr, False)

        if not order.isdigit() or int(order) == 0:
            raise Error.XMLError(BAD_XML, f"Order atribut musí být celé kladné číslo! Nalezeno: {order}!")

        return int(order)


    @staticmethod
    def createOperand(number : int, content : str, type):
        """Operand object factory, chooses the right subclass of Operand"""

        if type in Data.Type:
            value = Lang.str2value(type, content) # Getting corresponding value
            return Literal(number, content, type, value)

        elif type == Operand.Type.VAR:
            frame, name = Lang.splitVarName(content)
            frameMark = Lang.str2frame(frame)
            return Variable(number, content, frameMark, name)

        elif type == Operand.Type.TYPE:
            convType = Lang.getType(content)
            return Type(number, content, convType)

        else:
            return Operand(number, type, content)

    @staticmethod
    def checkNumberingOfOperands(opcode : str, order : int, operands : list):
        """Check numbering of arguments in sorted list (the it should be 
        increasing sequence without gaps), if there is mistake it raises exc.
        """

        for index, op in enumerate(operands):
            if index + 1 != op.getNumber():
                raise Error.XMLError(BAD_XML, f"Špatné číslování argumentů instrukce {opcode} (o. {order})!")


    @staticmethod
    def getOperands(opcode : str, order : int, instruction : xml.Node):
        """Gets operands of instruction in XML representation and converts it
        to list with operands objects

        Args:
            opcode (str): operational code of instruction
            order (int): order number of instruction
            instruction (xml.Node): XML representation of instruction

        Returns:
            (list): list with operand objects
        """

        xmlOps = __class__.safeGetChildren(__class__.ARG_TAG_RE, instruction)
        operands = []
        for op in xmlOps:
            tagName = op.tagName
            opNumber = int(re.sub(__class__.ARG_TAG_RE, r"\1", tagName))

            xmlType = __class__.safeGetAttribute(__class__.TYPE_ATTR, op, False)
            if not Lang.isType(xmlType):
                raise Error.XMLError(BAD_XML, f"Neznámý typ operandu '{xmlType}' instrukce {opcode} (o.: {order})!")

            type = Lang.getType(xmlType)
            content = __class__.safeGetData(op, canBeEmpty=True)
            if not Lang.isValidFormated(type, content):
                raise Error.XMLError(BAD_XML, f"Špatný formát operandu '{content}' instrukce {opcode} (o.: {str(order)})!")

            operands.append(__class__.createOperand(opNumber, content, type))

        operands.sort(key=Operand.getNumber)
        __class__.checkNumberingOfOperands(opcode, order, operands)

        return operands


    @staticmethod
    def createInstruction(opcode : str, order : int, ops):
        """Factory for making instruction objects, chooses the right subclass"""
        
        uOpcode = opcode.upper()

        if Lang.isLabelInstrucion(opcode):
            return Label(uOpcode, order, ops)
        elif Lang.isDebugInstruction(opcode):
            return Debug(uOpcode, order, ops, Lang.getFunction(uOpcode))
        else:
            return Executable(uOpcode, order, ops, Lang.getFunction(uOpcode))


    @staticmethod
    def convertInstructionSeq(instructionSeq : list) -> list:
        """Converts unsorted sequence of instruction represented by XML nodes
        to list with instruction objects

        Args:
            instructionSeq (list): unsorted list with instructions in XML repr.

        Returns:
            (list): Sorted list with instruction objects
        """

        instructionSeq.sort(key=__class__.safeGetOrder)

        lastOrder = None
        converted = []
        for i in instructionSeq:
            opcode = __class__.safeGetAttribute(__class__.OPCODE_ATTR, i, False)
            order = __class__.safeGetOrder(i)

            if lastOrder == order:
                raise Error.XMLError(BAD_XML, f"Nalezena duplicita atributu order! (u instrukce {opcode})!")
            if not Lang.isInstruction(opcode):
                raise Error.XMLError(BAD_XML, f"Neznámý operační kód '{opcode}'!")

            operands = __class__.getOperands(opcode, order, i)
            convInstr = __class__.createInstruction(opcode, order, operands)
            converted.append(convInstr)

            lastOrder = order

        return converted


    @staticmethod
    def getXMLInstructionSeq(xmlSrc) -> list:
        """Creates unsorted list with instructions represented by XML nodes
        
        Args:
            xmlSrc (opened file): XML source

        Returns:
            (list): unsorted list with instructions as XML nodes
        """

        root = xmlSrc.documentElement

        expRootType = xml.Node.ELEMENT_NODE
        if root.nodeType != expRootType or root.tagName != __class__.ROOT_TAG:
            raise Error.XMLError(BAD_XML, f"Očekáván kořenový XML tag {__class__.ROOT_TAG}, nebyl nalezen!")

        lang = __class__.safeGetAttribute(__class__.LANG_ATTR, root, False)
        if lang.upper() == __class__.LANGUAGE.upper(): # Checking ippcode22 attribute (case insensitive)
            raise Error.XMLError(BAD_XML, f"Neplatný obsah atributu '{__class__.LANG_ATTR}', očekáváno '{__class__.LANGUAGE}'!")


        instructions = __class__.safeGetChildren(__class__.INSTR_TAG, root)

        return instructions


    def createProgram(self, instructions):
        """Creates program object
        
        Args:
            instructions (list): list of instructions, that 
                should be executed in runtime

        Returns:
            (Program): program object with contaning given instructions
        """

        return Program(self.config, instructions)


    def parse(self):
        """Parses XML source (specified in config dict.) and creates
        corresponding program object
        """

        try:
            xmlSource = xml.parse(self.config["sourceOpened"])
        except expat.ExpatError as e:
            raise Error.XMLError(NOT_WELLFORMED, f"Špatně formátovaný XML zdroj ({str(e.lineno)}, {e.offset})!")
        except:
            raise Error.XMLError(NOT_WELLFORMED)

        xmlInstructionSequence = __class__.getXMLInstructionSeq(xmlSource)
        instructions = __class__.convertInstructionSeq(xmlInstructionSequence)

        program = self.createProgram(instructions)

        return program