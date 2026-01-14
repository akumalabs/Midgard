import api from '@/lib/axios';
import type { Server, ServerStatus, ApiResponse } from '@/types/models';

// Admin server API
export const adminServerApi = {
    list: async (filters?: { status?: string; node_id?: number; user_id?: number }): Promise<Server[]> => {
        const response = await api.get<ApiResponse<Server[]>>('/admin/servers', { params: filters });
        return response.data.data;
    },

    get: async (id: number): Promise<Server> => {
        const response = await api.get<ApiResponse<Server>>(`/admin/servers/${id}`);
        return response.data.data;
    },

    create: async (data: {
        user_id: number;
        node_id: number;
        name: string;
        hostname?: string;
        cpu: number;
        memory: number;
        disk: number;
        template_vmid: string;
        bandwidth_limit?: number;
    }): Promise<Server> => {
        const response = await api.post<ApiResponse<Server>>('/admin/servers', data);
        return response.data.data;
    },

    update: async (id: number, data: Partial<Server>): Promise<Server> => {
        const response = await api.put<ApiResponse<Server>>(`/admin/servers/${id}`, data);
        return response.data.data;
    },

    delete: async (id: number): Promise<void> => {
        await api.delete(`/admin/servers/${id}`);
    },

    power: async (id: number, action: 'start' | 'stop' | 'restart' | 'shutdown' | 'reset'): Promise<{ status: string }> => {
        const response = await api.post(`/admin/servers/${id}/power`, { action });
        return response.data.data;
    },

    status: async (id: number): Promise<ServerStatus> => {
        const response = await api.get<ApiResponse<ServerStatus>>(`/admin/servers/${id}/status`);
        return response.data.data;
    },
};

// Client server API
export const clientServerApi = {
    list: async (): Promise<Server[]> => {
        const response = await api.get<ApiResponse<Server[]>>('/client/servers');
        return response.data.data;
    },

    get: async (uuid: string): Promise<Server> => {
        const response = await api.get<ApiResponse<Server>>(`/client/servers/${uuid}`);
        return response.data.data;
    },

    status: async (uuid: string): Promise<ServerStatus> => {
        const response = await api.get<ApiResponse<ServerStatus>>(`/client/servers/${uuid}/status`);
        return response.data.data;
    },

    power: async (uuid: string, action: 'start' | 'stop' | 'restart' | 'shutdown' | 'kill'): Promise<{ status: string }> => {
        const response = await api.post(`/client/servers/${uuid}/power`, { action });
        return response.data.data;
    },

    console: async (uuid: string): Promise<{ ticket: string; port: number; url: string }> => {
        const response = await api.get(`/client/servers/${uuid}/console`);
        return response.data.data;
    },

    // Settings API (Convoy pattern)
    updatePassword: async (uuid: string, password: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/password`, { password });
        return response.data;
    },

    mountIso: async (uuid: string, storage: string, iso: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/iso/mount`, { storage, iso });
        return response.data;
    },

    unmountIso: async (uuid: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/iso/unmount`);
        return response.data;
    },

    // Snapshots API
    listSnapshots: async (uuid: string): Promise<any[]> => {
        const response = await api.get(`/client/servers/${uuid}/snapshots`);
        return response.data.data;
    },

    createSnapshot: async (uuid: string, name: string, description?: string, includeRam?: boolean): Promise<{ message: string; upid: string }> => {
        const response = await api.post(`/client/servers/${uuid}/snapshots`, {
            name,
            description,
            include_ram: includeRam
        });
        return response.data;
    },

    rollbackSnapshot: async (uuid: string, name: string): Promise<{ message: string; upid: string }> => {
        const response = await api.post(`/client/servers/${uuid}/snapshots/${name}/rollback`);
        return response.data;
    },

    deleteSnapshot: async (uuid: string, name: string): Promise<{ message: string; upid: string }> => {
        const response = await api.delete(`/client/servers/${uuid}/snapshots/${name}`);
        return response.data;
    },

    // Reinstall
    reinstall: async (uuid: string, templateId: number, password: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/reinstall`, {
            template_id: templateId,
            password
        });
        return response.data;
    },
};
