# Icinga Status Bundle

A [Symfony](https://symfony.com/) bundle that obtains and displays the status of hosts and services monitored by an
[Icinga 2](https://www.icinga.com/products/icinga-2/) instance.

Requires a connection to the database used by the Icinga instance.

## Current views

### Status List

A combination of the *Hosts* and *Services* overviews of [Icinga Web 2](https://www.icinga.com/products/icinga-web-2/).
Prominently lists the hosts and services that currently require attention.

### Service Matrix

Similar to Icinga Web 2’s *Service Grid*. Each row is a host, each column is a service group, the intersection displays
the “worst” status of the services in that service group on that host.
