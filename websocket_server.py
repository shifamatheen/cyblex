import asyncio
import websockets
import json
import mysql.connector
from datetime import datetime
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Database configuration
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'cyblex')
}

# Store active connections
connected_clients = {}

async def handle_message(websocket, message):
    try:
        data = json.loads(message)
        message_type = data.get('type')
        
        if message_type == 'auth':
            # Store client connection
            query_id = data.get('queryId')
            user_id = data.get('userId')
            user_type = data.get('userType')
            
            if query_id not in connected_clients:
                connected_clients[query_id] = {}
            connected_clients[query_id][user_id] = {
                'websocket': websocket,
                'user_type': user_type
            }
            
            # Send acknowledgment
            await websocket.send(json.dumps({
                'type': 'auth_success',
                'message': 'Connected successfully'
            }))
            
        elif message_type == 'message':
            query_id = data.get('queryId')
            user_id = data.get('userId')
            message_text = data.get('message')
            
            # Save message to database
            conn = mysql.connector.connect(**DB_CONFIG)
            cursor = conn.cursor()
            
            cursor.execute("""
                INSERT INTO messages (query_id, sender_id, message, created_at)
                VALUES (%s, %s, %s, %s)
            """, (query_id, user_id, message_text, datetime.now()))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            # Broadcast message to all clients in the same query
            if query_id in connected_clients:
                for client_id, client_data in connected_clients[query_id].items():
                    if client_data['websocket'] != websocket:  # Don't send back to sender
                        await client_data['websocket'].send(json.dumps({
                            'type': 'message',
                            'sender_id': user_id,
                            'message': message_text,
                            'created_at': datetime.now().isoformat()
                        }))
    
    except Exception as e:
        print(f"Error handling message: {e}")
        await websocket.send(json.dumps({
            'type': 'error',
            'message': str(e)
        }))

async def handle_connection(websocket, path):
    try:
        async for message in websocket:
            await handle_message(websocket, message)
    except websockets.exceptions.ConnectionClosed:
        # Remove client from connected_clients when disconnected
        for query_id in connected_clients:
            for user_id, client_data in list(connected_clients[query_id].items()):
                if client_data['websocket'] == websocket:
                    del connected_clients[query_id][user_id]
                    if not connected_clients[query_id]:
                        del connected_clients[query_id]

async def main():
    server = await websockets.serve(handle_connection, 'localhost', 8765)
    print("WebSocket server started on ws://localhost:8765")
    await server.wait_closed()

if __name__ == "__main__":
    asyncio.run(main()) 