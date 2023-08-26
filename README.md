KeeperFX MasterServer
=====================

This is the KeeperFX masterserver that handles a list of online multiplayer lobbies.

It's a simple plaintext TCP server that communicates using JSON data structures.
It does not rely on a continuous open socket.
When a lobby is created the client receives a lobby token that can be used to update the details of the lobby.



## Requirements

- PHP 8.1+ (CLI)
- Composer



## Setup
```
git clone https://github.com/dkfans/keeperfx-masterserver
cd keeperfx-masterserver
composer install
```

To daemonize the server on a VPS you can use the [example systemd service script](docs/keeperfx-masterserver.service) 



## Usage
```
php server.php 127.0.0.1 5566
```

Use the ip address `0.0.0.0` to listen on all network interfaces. (Public server).  
Use `127.0.0.1` to only listen on the local loopback interface.

There is also a `-v` command line option to increase the verbosity:
```
php server.php 127.0.0.1 5566 -v
```



## Roadmap

- Keeperfx.net account integration
- Gather game stats
- ...



## Responsible security disclosure

Please report any security issues to yani[@](@)keeperfx.net.



## License

GNUv3
