<?php

namespace App\Services;

use App\Models\AwsConnection;
use App\Models\AwsOptimizationFinding;
use App\Models\AwsResource;

class AwsOptimizationAnalyzer
{
    private const SENSITIVE_PORTS = [
        22 => 'SSH', 3389 => 'RDP', 3306 => 'MySQL', 5432 => 'PostgreSQL', 1521 => 'Oracle',
        6379 => 'Redis', 27017 => 'MongoDB', 9200 => 'OpenSearch/Elasticsearch', 11211 => 'Memcached',
    ];

    public function analyze(AwsConnection $connection): int
    {
        $resources = $connection->resources()->where('state', '!=', 'stale')->get();
        $elasticIps = $resources->where('type', 'elastic-ip');
        $publicEnis = $elasticIps->pluck('metadata.network_interface_id')->filter()->unique();
        $activeKeys = [];

        foreach ($resources->where('type', 'security-group') as $group) {
            foreach ($this->securityGroupFindings($group, $publicEnis->all()) as $finding) {
                $activeKeys[] = $this->store($connection, $group, $finding);
            }
        }
        foreach ($elasticIps as $elasticIp) {
            foreach ($this->elasticIpFindings($elasticIp, $resources) as $finding) {
                $activeKeys[] = $this->store($connection, $elasticIp, $finding);
            }
        }

        $stale = AwsOptimizationFinding::query()->where('aws_connection_id', $connection->id)->where('status', 'active');
        $activeKeys === [] ? $stale->update(['status' => 'resolved', 'resolved_at' => now()])
            : $stale->whereNotIn('finding_key', $activeKeys)->update(['status' => 'resolved', 'resolved_at' => now()]);

        return count($activeKeys);
    }

    private function securityGroupFindings(AwsResource $group, array $publicEnis): array
    {
        $metadata = $group->metadata ?? [];
        $associations = $metadata['network_interface_ids'] ?? [];
        $internetFacing = collect($associations)->intersect($publicEnis)->isNotEmpty();
        $findings = [];
        $openAll = [];
        $sensitive = [];

        foreach ($metadata['ingress_rules'] ?? [] as $rule) {
            $sources = array_merge($rule['ipv4_ranges'] ?? [], $rule['ipv6_ranges'] ?? []);
            $publicSources = array_values(array_intersect($sources, ['0.0.0.0/0', '::/0']));
            if ($publicSources === []) {
                continue;
            }
            if (($rule['protocol'] ?? '') === '-1') {
                $openAll[] = ['sources' => $publicSources, 'rule_id' => $rule['rule_id'] ?? null];

                continue;
            }
            foreach (self::SENSITIVE_PORTS as $port => $label) {
                if ($this->ruleIncludesPort($rule, $port)) {
                    $sensitive[$port] = ['port' => $port, 'service' => $label, 'sources' => $publicSources, 'rule_id' => $rule['rule_id'] ?? null];
                }
            }
        }

        if ($openAll !== []) {
            $findings[] = [
                'code' => 'sg-open-all', 'category' => 'security-group', 'severity' => 'critical',
                'confidence' => $internetFacing ? 'high' : 'medium',
                'title' => "{$group->name} allows all inbound traffic from the internet",
                'recommendation' => 'Remove the unrestricted rule and allow only required ports from trusted CIDRs or source security groups.',
                'evidence' => ['rules' => $openAll, 'associated_network_interfaces' => $associations, 'publicly_addressed_resource' => $internetFacing],
            ];
        }
        if ($sensitive !== []) {
            $labels = collect($sensitive)->pluck('service')->implode(', ');
            $findings[] = [
                'code' => 'sg-public-sensitive-'.implode('-', array_keys($sensitive)), 'category' => 'security-group',
                'severity' => collect($sensitive)->contains(fn (array $item): bool => in_array($item['port'], [22, 3389], true)) ? 'critical' : 'high',
                'confidence' => $internetFacing ? 'high' : 'medium',
                'title' => "{$group->name} exposes sensitive services: {$labels}",
                'recommendation' => 'Restrict administrative and database ports to approved CIDRs, VPN access, SSM, or source security groups.',
                'evidence' => ['exposed_services' => array_values($sensitive), 'associated_network_interfaces' => $associations, 'publicly_addressed_resource' => $internetFacing],
            ];
        }
        if ($associations === [] && ($metadata['group_name'] ?? '') !== 'default') {
            $findings[] = [
                'code' => 'sg-unused', 'category' => 'security-group', 'severity' => 'low', 'confidence' => 'high',
                'title' => "{$group->name} is not attached to a network interface",
                'recommendation' => 'Confirm that the group is not referenced by another service or launch template, then remove it if it is no longer required.',
                'evidence' => ['network_interface_count' => 0],
            ];
        }
        if (($metadata['group_name'] ?? '') === 'default' && $associations !== []) {
            $findings[] = [
                'code' => 'sg-default-in-use', 'category' => 'security-group', 'severity' => 'medium', 'confidence' => 'high',
                'title' => 'The default Security Group is attached to workloads',
                'recommendation' => 'Create purpose-specific Security Groups and migrate workloads away from the default group.',
                'evidence' => ['associated_network_interfaces' => $associations],
            ];
        }

        return $findings;
    }

    private function elasticIpFindings(AwsResource $elasticIp, $resources): array
    {
        $metadata = $elasticIp->metadata ?? [];
        if (empty($metadata['association_id'])) {
            return [[
                'code' => 'eip-unassociated', 'category' => 'elastic-ip', 'severity' => 'medium', 'confidence' => 'high',
                'title' => "Elastic IP {$elasticIp->name} is unassociated",
                'recommendation' => 'Confirm ownership and release the address if it is no longer required to avoid ongoing public IPv4 charges.',
                'evidence' => ['public_ip' => $metadata['public_ip'] ?? null, 'allocation_id' => $elasticIp->resource_id],
            ]];
        }
        $instanceId = $metadata['instance_id'] ?? null;
        $instance = $instanceId ? $resources->firstWhere('resource_id', $instanceId) : null;
        if ($instance && $instance->state === 'stopped') {
            return [[
                'code' => 'eip-stopped-instance', 'category' => 'elastic-ip', 'severity' => 'low', 'confidence' => 'high',
                'title' => "Elastic IP {$elasticIp->name} is attached to a stopped instance",
                'recommendation' => 'Review whether the static address must be retained while the instance is stopped.',
                'evidence' => ['public_ip' => $metadata['public_ip'] ?? null, 'instance_id' => $instanceId, 'instance_state' => 'stopped'],
            ]];
        }

        return [];
    }

    private function ruleIncludesPort(array $rule, int $port): bool
    {
        $from = $rule['from_port'] ?? null;
        $to = $rule['to_port'] ?? null;

        return $from !== null && $to !== null && $port >= $from && $port <= $to;
    }

    private function store(AwsConnection $connection, AwsResource $resource, array $finding): string
    {
        $key = substr(hash('sha256', $resource->region.'|'.$resource->resource_id.'|'.$finding['code']), 0, 64);
        unset($finding['code']);
        $existing = AwsOptimizationFinding::query()->where('aws_connection_id', $connection->id)->where('finding_key', $key)->first();
        AwsOptimizationFinding::query()->updateOrCreate(
            ['aws_connection_id' => $connection->id, 'finding_key' => $key],
            array_merge($finding, [
                'resource_id' => $resource->resource_id, 'resource_arn' => $resource->arn,
                'resource_type' => $resource->type, 'region' => $resource->region,
                'status' => 'active', 'first_seen_at' => $existing?->first_seen_at ?? now(),
                'last_seen_at' => now(), 'resolved_at' => null,
            ]),
        );

        return $key;
    }
}
