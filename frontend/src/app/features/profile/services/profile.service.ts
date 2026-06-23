import { Injectable } from '@angular/core';
import { of } from 'rxjs';
import type { ProfileSnapshot } from '../models/profile.model';

@Injectable({ providedIn: 'root' })
export class ProfileService {
  loadProfile() {
    return of<ProfileSnapshot>({
      fullName: 'Customer User',
      email: 'customer.example.com',
    });
  }
}

