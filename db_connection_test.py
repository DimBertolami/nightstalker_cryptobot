
import pymysql

try:
    connection = pymysql.connect(
        host='localhost',
        user='root',
        password='',
        database='nightstalker_db',
        cursorclass=pymysql.cursors.DictCursor
    )
    print("Connection successful!")
    connection.close()
except Exception as e:
    
