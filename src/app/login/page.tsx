'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { useAuthStore } from '@/lib/store/auth-store';
import { SEED_USERS } from '@/lib/seed-data';

export default function LoginPage() {
  const router = useRouter();
  const { setUser } = useAuthStore();
  const [selectedRole, setSelectedRole] = useState<string>('owner');

  const handleDemoLogin = () => {
    const user = SEED_USERS.find(u => u.role === selectedRole) || SEED_USERS[0];
    setUser(user);
    router.push('/dashboard');
  };

  return (
    <div className="min-h-screen bg-[#F5F6FA] flex items-center justify-center p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-8">
        {/* Logo */}
        <div className="flex justify-center mb-8">
          <div className="flex items-center gap-3">
            <div className="w-14 h-14 bg-[#FBBC03] rounded-xl flex items-center justify-center font-bold text-[#012650] text-2xl">
              TG
            </div>
            <div>
              <h1 className="text-2xl font-bold text-[#012650]">Twins Garage</h1>
              <p className="text-sm text-[#3B445C]">Doors Dashboard</p>
            </div>
          </div>
        </div>

        {/* Demo Login */}
        <div className="space-y-6">
          <div>
            <h2 className="text-lg font-semibold text-[#012650] mb-1">Welcome Back</h2>
            <p className="text-sm text-[#3B445C]">Sign in to access your dashboard</p>
          </div>

          {/* Email field (disabled for demo) */}
          <div>
            <label className="block text-sm font-medium text-[#3B445C] mb-1">Email</label>
            <input
              type="email"
              placeholder="your@email.com"
              disabled
              className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-[#3B445C]"
            />
          </div>

          {/* Password field (disabled for demo) */}
          <div>
            <label className="block text-sm font-medium text-[#3B445C] mb-1">Password</label>
            <input
              type="password"
              placeholder="••••••••"
              disabled
              className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-[#3B445C]"
            />
          </div>

          <div className="border-t border-gray-100 pt-4">
            <p className="text-xs text-[#3B445C] mb-3 text-center font-medium uppercase tracking-wider">Demo Login</p>
            <div className="grid grid-cols-2 gap-2 mb-4">
              {[
                { role: 'owner', label: 'CEO / Owner' },
                { role: 'manager', label: 'Manager' },
                { role: 'technician', label: 'Technician' },
                { role: 'csr', label: 'CSR' },
              ].map(({ role, label }) => (
                <button
                  key={role}
                  onClick={() => setSelectedRole(role)}
                  className={`px-3 py-2 text-sm font-medium rounded-lg border transition-colors ${
                    selectedRole === role
                      ? 'bg-[#012650] text-white border-[#012650]'
                      : 'bg-white text-[#3B445C] border-gray-200 hover:border-[#FBBC03]'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          <Button onClick={handleDemoLogin} className="w-full" size="lg">
            Sign In as {selectedRole === 'owner' ? 'CEO' : selectedRole === 'csr' ? 'CSR' : selectedRole.charAt(0).toUpperCase() + selectedRole.slice(1)}
          </Button>
        </div>
      </div>
    </div>
  );
}
