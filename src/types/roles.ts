export type UserRole = 'technician' | 'csr' | 'manager' | 'owner';

export interface UserProfile {
  id: string;
  email: string;
  fullName: string;
  role: UserRole;
  avatarUrl?: string;
  managerId?: string;
  isActive: boolean;
  createdAt: string;
}

export interface TeamAssignment {
  managerId: string;
  technicianId: string;
  assignedAt: string;
}

export const ROLE_LABELS: Record<UserRole, string> = {
  technician: 'Technician',
  csr: 'CSR',
  manager: 'Manager',
  owner: 'Owner / CEO',
};

export const ROLE_HIERARCHY: Record<UserRole, number> = {
  technician: 0,
  csr: 0,
  manager: 1,
  owner: 2,
};

export function canViewUser(viewerRole: UserRole, viewerId: string, targetId: string, targetManagerId?: string): boolean {
  if (viewerRole === 'owner') return true;
  if (viewerRole === 'manager' && targetManagerId === viewerId) return true;
  return viewerId === targetId;
}
