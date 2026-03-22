'use client';

import { useState } from 'react';
import { Header } from '@/components/layout/header';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import { Modal } from '@/components/ui/modal';
import { SEED_USERS } from '@/lib/seed-data';
import { ROLE_LABELS } from '@/types/roles';
import type { UserProfile } from '@/types/roles';

const ROLE_VARIANTS: Record<string, 'info' | 'success' | 'warning' | 'default'> = {
  owner: 'success',
  manager: 'warning',
  technician: 'info',
  csr: 'default',
};

export default function UsersAdminPage() {
  const [isModalOpen, setIsModalOpen] = useState(false);

  const columns = [
    {
      key: 'name',
      header: 'Name',
      render: (row: UserProfile) => (
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-full bg-[#012650] flex items-center justify-center text-white text-xs font-medium">
            {row.fullName.split(' ').map(n => n[0]).join('')}
          </div>
          <div>
            <p className="font-medium text-[#012650]">{row.fullName}</p>
            <p className="text-xs text-[#3B445C]">{row.email}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'role',
      header: 'Role',
      render: (row: UserProfile) => (
        <Badge variant={ROLE_VARIANTS[row.role] || 'default'}>
          {ROLE_LABELS[row.role]}
        </Badge>
      ),
    },
    {
      key: 'manager',
      header: 'Reports To',
      render: (row: UserProfile) => {
        const manager = SEED_USERS.find(u => u.id === row.managerId);
        return <span className="text-[#3B445C]">{manager?.fullName || '—'}</span>;
      },
    },
    {
      key: 'status',
      header: 'Status',
      render: (row: UserProfile) => (
        <Badge variant={row.isActive ? 'success' : 'danger'}>
          {row.isActive ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      render: () => (
        <Button variant="ghost" size="sm">Edit</Button>
      ),
    },
  ];

  return (
    <div>
      <Header
        title="User Management"
        showDatePicker={false}
        actions={
          <Button onClick={() => setIsModalOpen(true)}>Add User</Button>
        }
      />

      <div className="p-6">
        <Card>
          <CardHeader>
            <CardTitle>Team Members</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <DataTable
              columns={columns}
              data={SEED_USERS}
              keyExtractor={(row) => row.id}
            />
          </CardContent>
        </Card>
      </div>

      <Modal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} title="Add Team Member">
        <form className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-[#3B445C] mb-1">Full Name</label>
            <input className="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#FBBC03] focus:border-transparent" />
          </div>
          <div>
            <label className="block text-sm font-medium text-[#3B445C] mb-1">Email</label>
            <input type="email" className="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#FBBC03] focus:border-transparent" />
          </div>
          <div>
            <label className="block text-sm font-medium text-[#3B445C] mb-1">Role</label>
            <select className="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#FBBC03] focus:border-transparent">
              <option value="technician">Technician</option>
              <option value="csr">CSR</option>
              <option value="manager">Manager</option>
              <option value="owner">Owner / CEO</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-[#3B445C] mb-1">Assign to Manager</label>
            <select className="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#FBBC03] focus:border-transparent">
              <option value="">None</option>
              {SEED_USERS.filter(u => u.role === 'manager').map(m => (
                <option key={m.id} value={m.id}>{m.fullName}</option>
              ))}
            </select>
          </div>
          <div className="flex gap-3 pt-2">
            <Button variant="outline" type="button" onClick={() => setIsModalOpen(false)} className="flex-1">Cancel</Button>
            <Button type="button" onClick={() => setIsModalOpen(false)} className="flex-1">Save</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
