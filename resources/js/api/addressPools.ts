import api from '@/lib/axios';

export interface AddressPool {
    id: number;
    name: string;
    gateway?: string;
    netmask?: string;
    dns_primary?: string;
    dns_secondary?: string;
    addresses_count?: number;
    available_count?: number;
    nodes?: any[];
    addresses?: Address[];
    created_at: string;
}

export interface Address {
    id: number;
    address: string;
    cidr: number;
    gateway: string;
    type: string;
    server_id?: number;
    server?: { id: number; uuid: string; name: string };
}

export const addressPoolApi = {
    async list(): Promise<AddressPool[]> {
        const response = await api.get('/admin/address-pools');
        return response.data.data;
    },

    async get(id: number): Promise<AddressPool> {
        const response = await api.get(`/admin/address-pools/${id}`);
        return response.data.data;
    },

    async create(data: { name: string; node_ids?: number[] }): Promise<AddressPool> {
        const response = await api.post('/admin/address-pools', data);
        return response.data.data;
    },

    async update(id: number, data: Partial<{ name: string; node_ids: number[] }>): Promise<AddressPool> {
        const response = await api.put(`/admin/address-pools/${id}`, data);
        return response.data.data;
    },

    async delete(id: number): Promise<void> {
        await api.delete(`/admin/address-pools/${id}`);
    },

    // Add individual addresses with full details
    async addAddresses(id: number, addresses: Array<{
        address: string;
        cidr: number;
        gateway: string;
        type?: string;
    }>): Promise<void> {
        await api.post(`/admin/address-pools/${id}/addresses`, { addresses });
    },

    // Add a range of IPs
    async addRange(id: number, data: {
        start: string;
        end: string;
        cidr: number;
        gateway: string;
    }): Promise<void> {
        await api.post(`/admin/address-pools/${id}/range`, data);
    },

    // Get addresses for a pool (uses show endpoint)
    async getAddresses(id: number): Promise<Address[]> {
        const response = await api.get(`/admin/address-pools/${id}`);
        return response.data.data.addresses || [];
    },

    async deleteAddress(addressId: number): Promise<void> {
        await api.delete(`/admin/addresses/${addressId}`);
    },
};
