import xml.dom.minidom as xml
import xml.parsers.expat as expat
import sys

from curses.ascii import isdigit
from errors import *

class IParser:
    ROOT_TAG = "program"
    INSTRUCTION_TAG = "instruction"
    ORDER_ATTR = "order"


    def __init__(self, config):
        self.config = config

    @staticmethod
    def getOrder(el):
        return int(el.getAttribute("order"))

    @staticmethod
    def checkOrderAttribute(instructions):
        '''Checks format of order attributes (it must be positive integer)'''
        for i in instructions:
            if not i.hasAttribute(IParser.ORDER_ATTR):
                raiseError(BAD_XML, "Instruction element missing order attribute!")
            if not i.getAttribute(IParser.ORDER_ATTR).isdigit():
                raiseError(BAD_XML, "Bad formatted order attribute '" + i.getAttribute(IParser.ORDER_ATTR) + "'!")

    @staticmethod
    def checkOrderUniqueness(sortedInstructionList):
        '''Check if every order number is unique'''
        lastOrder = None
        for i in sortedInstructionList:
            order = int(i.getAttribute(IParser.ORDER_ATTR))

            if lastOrder == order:
                raiseError(BAD_XML, "Repeating order number " + str(order) + "!")
            
            lastOrder = order


    def parse(self):
        '''Parses input XML represenation and returns list with instructions (sorted by order)'''

        try:
            document = xml.parse(self.config["sourceOpened"])
        except expat.ExpatError:
            raiseError(NOT_WELLFORMED, "XML source '" + self.config["source"] + "' is not well-formed!")

        root, *other = document.getElementsByTagName(IParser.ROOT_TAG)
        if len(other) > 0:
            raiseError(BAD_XML, "Only one root XML element expected! Found more roots!")
        
        instructionList = []
        for node in root.childNodes:
            if node.nodeType == xml.Node.ELEMENT_NODE:
                if node.tagName == IParser.INSTRUCTION_TAG:
                    instructionList.append(node)
                else:
                    raiseError(BAD_XML, "Unexpected tag <" + node.tagName + ">! Expected <" + IParser.INSTRUCTION_TAG + ">!")
            else:
                print(node)

        IParser.checkOrderAttribute(instructionList)
        instructionList.sort(key=IParser.getOrder)
        IParser.checkOrderUniqueness(instructionList)

        return instructionList