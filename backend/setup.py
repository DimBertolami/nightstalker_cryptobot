from setuptools import setup, find_packages

setup(
    name='cryptobot-backend',
    version='1.0.0',
    packages=find_packages(),
    install_requires=[
        'flask',
        'flask-cors',
        'sqlalchemy',
        'pandas',
        'numpy',
        'requests',
        'cachetools'
    ],
    python_requires='>=3.8'
)
