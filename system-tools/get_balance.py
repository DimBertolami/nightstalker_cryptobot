from bitvavo_class import BitvavoRestClient

# Replace with your actual API key and secret
API_KEY = 'ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37'
API_SECRET = '28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b'

# Create an instance of the client
client = BitvavoRestClient(api_key=API_KEY, api_secret=API_SECRET)

# Call the get_balance method to retrieve account balances
balance = client.get_balance()

# Print the balance response
print(balance)