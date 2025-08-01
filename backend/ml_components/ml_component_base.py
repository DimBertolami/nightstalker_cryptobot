from abc import ABC, abstractmethod

class MLComponentBase(ABC):
    """
    Base class for all ML components including models, trainers, indicators, and strategies.
    Defines common interface methods to ensure consistency and reusability.
    """

    def __init__(self):
        self.initialized = False

    @abstractmethod
    def initialize(self, *args, **kwargs):
        """
        Initialize the component, e.g., load data, set parameters.
        """
        pass

    @abstractmethod
    def train(self, *args, **kwargs):
        """
        Train the model or component.
        """
        pass

    @abstractmethod
    def evaluate(self, *args, **kwargs):
        """
        Evaluate the model or component.
        """
        pass

    @abstractmethod
    def save(self, *args, **kwargs):
        """
        Save the model or component state.
        """
        pass

    @abstractmethod
    def load(self, *args, **kwargs):
        """
        Load the model or component state.
        """
        pass
