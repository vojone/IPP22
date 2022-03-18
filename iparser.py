import xml.dom.minidom as xml
import xml.parsers.expat as expat
import re

from errors import *
from program import Data, Executable, Instruction, Label, Literal, Operand, Type
from program import Program, ProgramContext, Variable
from lang import Lang



class SAnalayzer:
    def __init__(self):
        self.reset()

    def reset(self):
        self.jumpTargets = {}
        self.fakeCtx = ProgramContext()


    def resolveSAction(self, inst : Instruction, index : int, operands : list):
        opcode = inst.getOpCode()
        order = inst.getOrder()
        
        leadingOp = None
        if operands:
            leadingOp = operands[0]

        if Lang.isLabelInstrucion(opcode):
            self.fakeCtx.addLabel(leadingOp.getContent(), index)
        elif Lang.isJumpInstruction(opcode):
            self.jumpTargets[leadingOp.getContent()] = order
        elif Lang.isNewVarInstruction(opcode): # TODO - Statically, it detects redefinition only for global vars
            if leadingOp.getFrame() == Variable.Frame.GLOBAL:
                self.fakeCtx.addVar(leadingOp)


    def checkSemantics(self, program : Program):
        self.reset()
        for index, i in enumerate(program.getInstructions()):
            opcode = i.getOpCode()
            order = i.getOrder()
            operands = i.getOperands()
            expOperandSymbols = Lang.getOperandTypes(opcode)

            nOperands = len(operands)
            nExpOp = len(expOperandSymbols)
            if nOperands != nExpOp:
                raise Error.SemanticError(SEMANTIC_ERROR, "Instruction "+opcode+" (o. "+str(order)+") expects "+str(nExpOp)+" operands, found "+ str(nOperands)+"!")

            for index, op in enumerate(operands):
                if not Lang.isOperandCompatible(op, expOperandSymbols[index]):
                    raise Error.SemanticError(SEMANTIC_ERROR, "Incomatible operand type of "+opcode+"(o. "+str(order)+")! Expected "+expOperandSymbols[index]+", got "+Lang.op2Str(op))
        
            self.resolveSAction(i, index, operands)

        for jt in self.jumpTargets:
            if jt not in self.fakeCtx.getLabelMap():
                raise Error.SemanticError(SEMANTIC_ERROR, "Jump to undefined label '"+jt+"'! (at order "+self.jumpTargets[jt]+")")
            


class IParser:
    ROOT_TAG = "program"
    INSTR_TAG = "instruction"
    ARG_TAG_RE = "arg(\d+)"

    ORDER_ATTR = "order"
    OPCODE_ATTR = "opcode"
    TYPE_ATTR = "type"


    def __init__(self, config : dict):
        self.config = config


    @staticmethod
    def safeGetAttribute(attrName : str, node : xml.Node, canBeEmpty = False):
        if not node.hasAttribute(attrName):
            raise Error.XMLError(BAD_XML, "Expected attribute: "+attrName+", but it was not found!") 

        attr = node.getAttribute(attrName)

        if attr or canBeEmpty:
            return attr
        else:
            raise Error.XMLError(BAD_XML, "Unexpected empty attribute "+attrName+"!") 


    @staticmethod
    def safeGetChildren(childTagNameRE : str, parentNode : xml.Node):
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
                    raise Error.XMLError(BAD_XML, "Unexpected XML tag "+n.tagName+"!")
            else:
                raise Error.XMLError(BAD_XML, "Unexpected XML element of type "+n.nodeName+"!")
        
        return children


    @staticmethod
    def safeGetData(node : xml.Node, canBeEmpty = False) -> str:
        if not node.firstChild and not canBeEmpty:
            raise Error.XMLError(BAD_XML, "Expected text node inside tag "+node.tagName+"!")
        elif not node.firstChild and canBeEmpty:
            return ""

        if node.firstChild.nodeType != xml.Node.TEXT_NODE:
            raise Error.XMLError(BAD_XML, "Expected text node inside tag "+node.tagName+", but another content was found!")
        elif len(node.childNodes) > 1:
            for index, ch in enumerate(node.childNodes):
                if index == 0:
                    continue
                if ch.nodeType == xml.Node.COMMENT_NODE:
                    raise Error.XMLError(BAD_XML, "Expected ONLY one text node inside tag "+node.tagName+", but another content was found!")
        
        data = node.firstChild.data.strip()
        if not data and not canBeEmpty:
            raise Error.XMLError(BAD_XML, "Expected not empty string inside tag "+node.tagName+"!")

        return data


    @staticmethod
    def safeGetOrder(instruction : xml.Node):
        order = __class__.safeGetAttribute(__class__.ORDER_ATTR, instruction)

        if not order.isdigit() or int(order) == 0:
            raise Error.XMLError(BAD_XML, "Order attribute must be positive integer! Found: "+order+"!")

        return int(order)


    @staticmethod
    def createOperand(content : str, type):
        if type in Data.Type:
            value = Lang.str2value(type, content) # Getting corresponding value
            return Literal(content, type, value)

        elif type == Operand.Type.VAR:
            frame, name = Lang.splitVarName(content)
            frameMark = Lang.str2frame(frame)
            return Variable(content, frameMark, name)

        elif type == Operand.Type.TYPE:
            convType = Lang.getType(content)
            return Type(content, convType)

        else:
            return Operand(type, content)


    @staticmethod
    def getOperands(opcode : str, order : int, instruction : xml.Node):
        xmlOps = __class__.safeGetChildren(__class__.ARG_TAG_RE, instruction)

        operands = []
        for index, op in enumerate(xmlOps):
            tagName = op.tagName
            if int(re.sub(__class__.ARG_TAG_RE, r"\1", tagName)) != index + 1:
                raise Error.XMLError(BAD_XML, "Bad numbering of arguments of instruction "+opcode+" (ord.: "+str(order)+")!")

            xmlType = __class__.safeGetAttribute(__class__.TYPE_ATTR, op)
            if not Lang.isType(xmlType):
                raise Error.XMLError(BAD_XML, "Unknown argument type '"+xmlType+"' of instruction "+opcode+" (ord.: "+str(order)+")!")

            type = Lang.getType(xmlType)
            content = __class__.safeGetData(op, canBeEmpty=True)
            if not Lang.isValidFormated(type, content):
                raise Error.XMLError(BAD_XML, "Bad format of operand '"+content+"' of instruction "+opcode+" (ord.: "+str(order)+")!")

            operands.append(__class__.createOperand(content, type))

        return operands


    @staticmethod
    def createInstruction(opcode : str, order : int, operands):
        if Lang.isLabelInstrucion(opcode):
            return Label(opcode, order, operands)
        else:
            return Executable(opcode, order, operands, Lang.getFunction(opcode))


    @staticmethod
    def convertInstructionSeq(instructionSeq : list):
        instructionSeq.sort(key=__class__.safeGetOrder)

        lastOrder = None
        converted = []
        for i in instructionSeq:
            opcode = __class__.safeGetAttribute(__class__.OPCODE_ATTR, i)
            order = __class__.safeGetOrder(i)

            if lastOrder == order:
                raise Error.XMLError(BAD_XML, "Found duplicit order attribute! (instruction "+opcode+")!")
            if not Lang.isInstruction(opcode):
                raise Error.XMLError(BAD_XML, "Unknown operational code '"+opcode+"'!")

            operands = __class__.getOperands(opcode, order, i)
            convInstr = __class__.createInstruction(opcode, order, operands)
            converted.append(convInstr)

            lastOrder = order

        return converted


    @staticmethod
    def getXMLInstructionSeq(xmlSrc):
        root = xmlSrc.documentElement

        expRootType = xml.Node.ELEMENT_NODE
        if root.nodeType != expRootType or root.tagName != __class__.ROOT_TAG:
            raise Error.XMLError(BAD_XML, "Expected root XML element "+ __class__.ROOT_TAG+", but it was not found!")

        instructions = __class__.safeGetChildren(__class__.INSTR_TAG, root)

        return instructions


    def createProgram(self, instructions):
        return Program(self.config["inputOpened"], instructions)


    def parse(self):
        try:
            xmlSource = xml.parse(self.config["sourceOpened"])
        except expat.ExpatError as e:
            raise Error.XMLError(NOT_WELLFORMED, "Not wellformed XML at "+str(e.lineno)+", "+str(e.offset)+"!")
        except:
            raise Error.XMLError(NOT_WELLFORMED)

        xmlInstructionSequence = __class__.getXMLInstructionSeq(xmlSource)
        instructions = __class__.convertInstructionSeq(xmlInstructionSequence)

        program = self.createProgram(instructions)

        return program