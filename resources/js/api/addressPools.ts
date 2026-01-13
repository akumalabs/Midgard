import api from '@/lib/axios';

export interface AddressPool {
    id: number;
    name: string;
    gateway: string;
    netmask: string;
    dns_primary: string;
    dns_secondary?: string;
    addresses_count?: number;
    available_count?: number;
    nodes?: any[];
    created_at: string;
    updated_at: string;
}

export interface Address {
    id: number;
    address_pool_id: number;
    server_id?: number;
    ip_address: string;
    is_primary: boolean;
    created_at: string;
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

    async create(data: Partial<AddressPool>): Promise<AddressPool> {
        const response = await api.post('/admin/address-pools', data);
        return response.data.data;
    },

    async update(id: number, data: Partial<AddressPool>): Promise<AddressPool> {
        const response = await api.put(`/admin/address-pools/${id}`, data);
        return response.data.data;
    },

    async delete(id: number): Promise<void> {
        await api.delete(`/admin/address-pools/${id}`);
    },

    async addAddresses(id: number, addresses: string[]): Promise<void> {
        await api.post(`/admin/address-pools/${id}/addresses`, { addresses });
    },

    async addRange(id: number, start: string, end: string): Promise<void> {
        await api.post(`/admin/address-pools/${id}/addresses/range`, { start, end });
    },

    async getAddresses(id: number): Promise<Address[]> {
        const response = await api.get(`/admin/address-pools/${id}/addresses`);
        return response.data.data;
    },

    async deleteAddress(poolId: number, addressId: number): Promise<void> {
        await api.delete(`/admin/address-pools/${poolId}/addresses/${addressId}`);
    },
};
